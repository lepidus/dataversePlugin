<?php

import('plugins.generic.dataverse.classes.exception.DataverseException');

import('plugins.generic.dataverse.classes.services.DataverseService');
import('plugins.generic.dataverse.dataverseAPI.DataverseClient');
import('plugins.generic.dataverse.classes.entities.Dataset');
import('plugins.generic.dataverse.classes.deposit.DepositRepository');
import('plugins.generic.dataverse.classes.deposit.DepositWorkflow');

use Illuminate\Database\Capsule\Manager as Capsule;

class DatasetService extends DataverseService
{
    private function createStudy(Submission $submission, string $persistentId): void
    {
        $contextId = $submission->getData('contextId');
        $configuration = DAORegistry::getDAO('DataverseConfigurationDAO')->get($contextId);
        $swordAPIBaseUrl = $configuration->getDataverseServerUrl() . '/dvn/api/data-deposit/v1.1/swordv2/';

        $dataverseStudyDAO = DAORegistry::getDAO('DataverseStudyDAO');
        $study = $dataverseStudyDAO->newDataObject();
        $study->setSubmissionId($submission->getId());
        $study->setPersistentId($persistentId);
        $study->setEditUri($swordAPIBaseUrl . 'edit/study/' . $persistentId);
        $study->setEditMediaUri($swordAPIBaseUrl . 'edit-media/study/' . $persistentId);
        $study->setStatementUri($swordAPIBaseUrl . 'statement/study/' . $persistentId);
        $study->setPersistentUri('https://doi.org/' . str_replace('doi:', '', $persistentId));
        $dataverseStudyDAO->insertStudy($study);
    }

    public function deposit(Submission $submission, Dataset $dataset): array
    {
        $dataverseClient = new DataverseClient();
        $files = array_values($dataset->getFiles());
        try {
            $completed = Capsule::table('dataverse_deposits')->where('submission_id', $submission->getId())
                ->where('state', 'complete')->first();
            if ($completed) {
                $study = DAORegistry::getDAO('DataverseStudyDAO')->getStudyBySubmissionId($submission->getId());
                if (!$study || $study->getPersistentId() !== $completed->persistent_id) {
                    throw new RuntimeException('The completed deposit association changed.');
                }
                if (!$files) {
                    $this->clearDepositedDrafts(
                        $submission->getId(),
                        json_decode($completed->manifest, true, 512, JSON_THROW_ON_ERROR)
                    );
                    return ['status' => 'Success'];
                }
            }
            $configuration = DAORegistry::getDAO('DataverseConfigurationDAO')->get($submission->getData('contextId'));
            $manifest = [];
            foreach ($files as $file) {
                $path = $file->getPath();
                $checksum = hash_file('sha256', $path);
                $size = filesize($path);
                if ($checksum === false || $size === false) {
                    throw new RuntimeException('Cannot read the deposit source file.');
                }
                $manifest[] = [
                    'sourceId' => $file->getId(),
                    'repository' => $configuration->getDataverseUrl(),
                    'name' => $file->getOriginalFileName(),
                    'size' => $size,
                    'sha256' => $checksum,
                ];
            }
            if (!$manifest) {
                throw new RuntimeException('The deposit has no source files.');
            }
            $workflow = new DepositWorkflow(new DepositRepository());
            $workflow->run(
                $submission->getId(),
                $manifest,
                function () use ($dataverseClient, $dataset): string {
                    return $dataverseClient->getDatasetActions()->create($dataset)->getPersistentId();
                },
                function (string $persistentId, int $key) use ($dataverseClient, $files): array {
                    $file = $files[$key];
                    return $dataverseClient->getDatasetFileActions()->add(
                        $persistentId,
                        $file->getOriginalFileName(),
                        $file->getPath()
                    );
                },
                function (string $persistentId) use ($submission): void {
                    $this->createStudy($submission, $persistentId);
                    $this->completeDeposit($submission, $persistentId);
                }
            );
        } catch (Throwable $e) {
            $state = 'unreserved';
            $doi = 'unknown';
            try {
                $operation = Capsule::table('dataverse_deposits')->where('submission_id', $submission->getId())->first();
                if ($operation) {
                    $state = $operation->state;
                    $doi = $operation->persistent_id ?? 'unknown';
                }
            } catch (Throwable $lookupError) {
                $state = 'unavailable';
            }
            error_log('Dataverse deposit requires attention: submission=' . $submission->getId()
                . ' state=' . $state
                . ' doi=' . $doi
                . ' category=' . ($e instanceof DataverseException ? $e->getFailureCategory() : 'local')
                . ' exception=' . get_class($e));
            return [
                'status' => 'Error',
                'message' => 'plugins.generic.dataverse.error.depositPending',
                'messageParams' => ['error' => __('plugins.generic.dataverse.error.depositPending')],
            ];
        }
        $this->clearDepositedDrafts($submission->getId(), $manifest);
        return ['status' => 'Success'];
    }

    private function clearDepositedDrafts(int $submissionId, array $manifest): void
    {
        $sourceIds = array_column($manifest, 'sourceId');
        $draftDao = DAORegistry::getDAO('DraftDatasetFileDAO');
        foreach ($draftDao->getBySubmissionId($submissionId) as $draftFile) {
            if (in_array($draftFile->getFileId(), $sourceIds, true)) {
                $draftDao->deleteById($draftFile->getId());
            }
        }
    }

    private function completeDeposit(Submission $submission, string $persistentId): void
    {
        $request = Application::get()->getRequest();
        $publication = $submission->getCurrentPublication();
        $dataStatementTypes = $publication->getData('dataStatementTypes');
        if (empty($dataStatementTypes)) {
            $dataStatementTypes = [DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED];
        }
        if (!in_array(DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED, $dataStatementTypes)) {
            $dataStatementTypes[] = DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED;
        }

        Services::get('publication')->edit(
            $publication,
            ['dataStatementTypes' => $dataStatementTypes],
            $request
        );

        $this->registerEventLog(
            $submission,
            'plugins.generic.dataverse.log.researchDataDeposited',
            ['persistentId' => $persistentId],
            SUBMISSION_LOG_SUBMISSION_SUBMIT
        );

    }

    public function update(array $data): void
    {
        try {
            $dataverseClient = new DataverseClient();
            $dataset = $dataverseClient->getDatasetActions()->get($data['persistentId']);
        } catch (DataverseException $e) {
            error_log('Dataverse error while getting dataset on dataset update: ' . $e->getMessage());
            return;
        }

        if ($dataset->isPublished()) {
            return;
        }

        foreach ($data as $name => $value) {
            if ($name == 'relationType') {
                $dataset->getRelatedPublication()->setData('RelationType', $value);
                continue;
            }

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

        $request = \Application::get()->getRequest();
        (new DepositRepository())->retire($submission->getId(), function () use ($study, $submission, $request): void {
            $currentStudy = DAORegistry::getDAO('DataverseStudyDAO')->getStudyBySubmissionId($submission->getId());
            if (!$currentStudy || $currentStudy->getId() !== $study->getId()) {
                throw new RuntimeException('The associated dataset changed before deletion.');
            }
            DAORegistry::getDAO('DataverseStudyDAO')->deleteStudy($study);

            $publication = $submission->getCurrentPublication();
            $dataStatementTypes = $publication->getData('dataStatementTypes');

            if (($key = array_search(DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED, $dataStatementTypes)) !== false) {
                unset($dataStatementTypes[$key]);
                sort($dataStatementTypes);
            }

            Services::get('publication')->edit($publication, ['dataStatementTypes' => $dataStatementTypes], $request);
        });

        $router = $request->getRouter();
        $handler = $router->getHandler();
        $userRoles = (array) $handler->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);

        if (in_array(ROLE_ID_MANAGER, $userRoles) && $deleteMessage) {
            $this->sendEmailToDatasetAuthor($request, $dataset, $submission, $deleteMessage);
        }

        $this->registerEventLog(
            $submission,
            'plugins.generic.dataverse.log.researchDataDeleted'
        );
    }

    public function disassociate(DataverseStudy $study): void
    {
        $submission = Services::get('submission')->get($study->getSubmissionId());
        $publication = $submission->getCurrentPublication();
        $dataStatementTypes = $publication->getData('dataStatementTypes');

        $request = \Application::get()->getRequest();
        (new DepositRepository())->retire($submission->getId(), function () use ($study, $publication, $dataStatementTypes, $request): void {
            $currentStudy = DAORegistry::getDAO('DataverseStudyDAO')->getStudyBySubmissionId($study->getSubmissionId());
            if (!$currentStudy || $currentStudy->getId() !== $study->getId()) {
                throw new RuntimeException('The associated dataset changed before disassociation.');
            }
            DAORegistry::getDAO('DataverseStudyDAO')->deleteStudy($study);

            if (($key = array_search(DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED, $dataStatementTypes)) !== false) {
                unset($dataStatementTypes[$key]);
                sort($dataStatementTypes);
            }

            Services::get('publication')->edit($publication, ['dataStatementTypes' => $dataStatementTypes], $request);
        });

        $this->registerEventLog(
            $submission,
            'plugins.generic.dataverse.log.researchDataDisassociate'
        );
    }

    public function associate(int $submissionId, string $persistentId): array
    {
        $submission = Services::get('submission')->get($submissionId);
        if (!$submission) {
            return ['status' => DataverseService::STATUS_NOT_FOUND, 'message' => 'api.404.resourceNotFound'];
        }

        $dataverseStudyDao = DAORegistry::getDAO('DataverseStudyDAO');
        $existingStudy = $dataverseStudyDao->getStudyBySubmissionId($submissionId);
        if ($existingStudy) {
            return ['status' => DataverseService::STATUS_ERROR, 'message' => 'plugins.generic.dataverse.error.submissionHasStudy'];
        }

        if (!preg_match('/^doi:10\.\d{4,9}\/[-._;()\/:A-Z0-9]+$/i', $persistentId)) {
            return ['status' => DataverseService::STATUS_ERROR, 'message' => 'plugins.generic.dataverse.error.invalidPersistentId'];
        }

        $existingStudyWithPersistentId = $dataverseStudyDao->getByPersistentId($persistentId);
        if ($existingStudyWithPersistentId) {
            return ['status' => DataverseService::STATUS_ERROR, 'message' => 'plugins.generic.dataverse.error.associate.alreadyAssociated'];
        }

        try {
            $dataverseClient = new DataverseClient();
            $dataset = $dataverseClient->getDatasetActions()->get($persistentId);
        } catch (DataverseException $e) {
            return ['status' => DataverseService::STATUS_NOT_FOUND, 'message' => 'plugins.generic.dataverse.error.associate.notFound'];
        }

        return Capsule::connection()->transaction(function () use ($submission, $submissionId, $persistentId): array {
            (new DepositRepository())->lockSubmission($submissionId);
            if (DAORegistry::getDAO('DataverseStudyDAO')->getStudyBySubmissionId($submissionId)
                || Capsule::table('dataverse_deposits')->where('submission_id', $submissionId)
                    ->where('state', '!=', 'retired')->exists()) {
                return ['status' => DataverseService::STATUS_ERROR, 'message' => 'plugins.generic.dataverse.error.depositPending'];
            }
            $this->createStudy($submission, $persistentId);
            return ['status' => DataverseService::STATUS_SUCCESS];
        });
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
