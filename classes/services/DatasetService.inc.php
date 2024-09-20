<?php

import('plugins.generic.dataverse.classes.services.DataverseService');
import('plugins.generic.dataverse.dataverseAPI.DataverseClient');
import('plugins.generic.dataverse.classes.entities.Dataset');

class DatasetService extends DataverseService
{
    public function deposit(Submission $submission, Dataset $dataset): void
    {
        $request = Application::get()->getRequest();
        $contextId = $request->getContext()->getId();

        try {
            $dataverseClient = new DataverseClient();
            $datasetIdentifier = $dataverseClient->getDatasetActions()->create($dataset);

            foreach ($dataset->getFiles() as $file) {
                $dataverseClient->getDatasetFileActions()->add(
                    $datasetIdentifier->getPersistentId(),
                    $file->getOriginalFileName(),
                    $file->getPath()
                );
            }
        } catch (DataverseException $e) {
            $this->registerEventLog(
                $submission,
                'plugins.generic.dataverse.error.depositFailed',
                ['error' => $e->getMessage()]
            );
            error_log('Dataverse API error: ' . $e->getMessage());
            throw $e;
        }

        $configuration = DAORegistry::getDAO('DataverseConfigurationDAO')->get($contextId);
        $swordAPIBaseUrl = $configuration->getDataverseServerUrl() . '/dvn/api/data-deposit/v1.1/swordv2/';

        $dataverseStudyDAO = DAORegistry::getDAO('DataverseStudyDAO');
        $study = $dataverseStudyDAO->newDataObject();
        $study->setSubmissionId($submission->getId());
        $study->setPersistentId($datasetIdentifier->getPersistentId());
        $study->setEditUri($swordAPIBaseUrl . 'edit/study/' . $datasetIdentifier->getPersistentId());
        $study->setEditMediaUri($swordAPIBaseUrl . 'edit-media/study/' . $datasetIdentifier->getPersistentId());
        $study->setStatementUri($swordAPIBaseUrl . 'statement/study/' . $datasetIdentifier->getPersistentId());
        $study->setPersistentUri('https://doi.org/' . str_replace('doi:', '', $datasetIdentifier->getPersistentId()));
        $dataverseStudyDAO->insertStudy($study);

        $publication = $submission->getCurrentPublication();
        $dataStatementTypes = $publication->getData('dataStatementTypes');
        if (empty($dataStatementTypes)) {
            $dataStatementTypes = [DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED];
        }
        if (!in_array(DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED, $dataStatementTypes)) {
            $dataStatementTypes[] = DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED;
        }

        $newPublication = Services::get('publication')->edit(
            $publication,
            ['dataStatementTypes' => $dataStatementTypes],
            $request
        );

        $this->registerEventLog(
            $submission,
            'plugins.generic.dataverse.log.researchDataDeposited',
            ['persistentId' => $datasetIdentifier->getPersistentId()],
            SUBMISSION_LOG_SUBMISSION_SUBMIT
        );

        DAORegistry::getDAO('DraftDatasetFileDAO')->deleteBySubmissionId($submission->getId());
    }

    public function update(array $data): void
    {
        $dataverseClient = new DataverseClient();
        $dataset = $dataverseClient->getDatasetActions()->get($data['persistentId']);

        foreach ($data as $name => $value) {
            $dataset->setData($name, $value);
        }

        $study = DAORegistry::getDAO('DataverseStudyDAO')->getByPersistentId($dataset->getPersistentId());
        $submission = Services::get('submission')->get($study->getSubmissionId());

        try {
            $dataverseClient->getDatasetActions()->update($dataset);
        } catch (DataverseException $e) {
            $this->registerAndNotifyError(
                $submission,
                'plugins.generic.dataverse.error.updateFailed',
                ['error' => $e->getMessage()]
            );
            return;
        }

        $this->registerEventLog(
            $submission,
            'plugins.generic.dataverse.log.researchDataUpdated'
        );
    }

    public function delete(DataverseStudy $study, ?string $deleteMessage): void
    {
        $submission = Services::get('submission')->get($study->getSubmissionId());

        try {
            $dataverseClient = new DataverseClient();

            $dataset = $dataverseClient->getDatasetActions()->get($study->getPersistentId());
            $dataverseName = $dataverseClient->getDataverseCollectionActions()->get()->getName();

            $dataverseClient->getDatasetActions()->delete($dataset->getPersistentId());
        } catch (DataverseException $e) {
            $this->registerAndNotifyError(
                $submission,
                'plugins.generic.dataverse.error.deleteFailed',
                ['error' => $e->getMessage()]
            );
            return;
        }

        DAORegistry::getDAO('DataverseStudyDAO')->deleteStudy($study);

        $publication = $submission->getCurrentPublication();
        $dataStatementTypes = $publication->getData('dataStatementTypes');

        if (($key = array_search(DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED, $dataStatementTypes)) !== false) {
            unset($dataStatementTypes[$key]);
            sort($dataStatementTypes);
        }

        $request = \Application::get()->getRequest();
        $newPublication = Services::get('publication')->edit(
            $publication,
            ['dataStatementTypes' => $dataStatementTypes],
            $request
        );

        $router = $request->getRouter();
        $handler = $router->getHandler();
        $userRoles = (array) $handler->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);

        if (in_array(ROLE_ID_MANAGER, $userRoles)) {
            $this->sendEmailToDatasetAuthor($request, $dataset, $submission, $deleteMessage);
        }

        $this->registerEventLog(
            $submission,
            'plugins.generic.dataverse.log.researchDataDeleted'
        );
    }

    public function publish(DataverseStudy $study): void
    {
        $submission = Services::get('submission')->get($study->getSubmissionId());

        try {
            $dataverseClient = new DataverseClient();

            $dataset = $dataverseClient->getDatasetActions()->get($study->getPersistentId());

            if ($dataset->isPublished()) {
                return;
            }

            $dataverseClient->getDatasetActions()->publish($study->getPersistentId());
        } catch (DataverseException $e) {
            $this->registerAndNotifyError(
                $submission,
                'plugins.generic.dataverse.error.publishFailed',
                ['error' => $e->getMessage()]
            );
            return;
        }

        $this->registerEventLog(
            $submission,
            'plugins.generic.dataverse.log.researchDataPublished',
            [],
            SUBMISSION_LOG_ARTICLE_PUBLISH
        );
    }

    private function sendEmailToDatasetAuthor(
        Request $request,
        Dataset $dataset,
        Submission $submission,
        ?string $deleteMessage
    ): void {
        $context = $request->getContext();

        $mailTemplate = 'DATASET_DELETE_NOTIFICATION';
        $datasetContact = $dataset->getContact();

        $mail = $this->getMailTemplate($mailTemplate, $context);

        $mail->setFrom($context->getData('contactEmail'), $context->getData('contactName'));

        $mail->setRecipients([[
            'name' => $datasetContact->getName(),
            'email' => $datasetContact->getEmail()
        ]]);

        $mail->setBody($deleteMessage);

        if (!$mail->send()) {
            import('classes.notification.NotificationManager');
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification($request->getUser()->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => __('email.compose.error')));
        } else {
            $this->logEmail($request, $mail, $submission);
        }
    }

    private function getMailTemplate(string $emailKey, Context $context = null): MailTemplate
    {
        import('lib.pkp.classes.mail.MailTemplate');
        return new MailTemplate($emailKey, null, $context, false);
    }

    private function logEmail(?Request $request, MailTemplate $mail, Submission $submission): void
    {
        $mail->replaceParams();

        import('lib.pkp.classes.log.SubmissionEmailLogEntry');
        $logDao = DAORegistry::getDAO('SubmissionEmailLogDAO');
        $entry = $logDao->newDataObject();

        $entry->setEventType(SUBMISSION_EMAIL_EDITOR_NOTIFY_AUTHOR);
        $entry->setAssocId($submission->getId());
        $entry->setDateSent(Core::getCurrentDate());

        if ($request) {
            $user = $request->getUser();
            $entry->setSenderId($user == null ? 0 : $user->getId());
        } else {
            $entry->setSenderId(0);
        }

        $entry->setSubject($mail->getSubject());
        $entry->setBody($mail->getBody());
        $entry->setFrom($mail->getFromString(false));
        $entry->setRecipients($mail->getRecipientString());
        $entry->setCcs($mail->getCcString());
        $entry->setBccs($mail->getBccString());

        $logEntryId = $logDao->insertObject($entry);
    }
}
