<?php

import('plugins.generic.dataverse.dataverseAPI.DataverseClient');

class DatasetService
{
    public function depositBySubmission(Submission $submission): void
    {
        import('plugins.generic.dataverse.classes.factories.SubmissionDatasetFactory');
        $datasetFactory = new SubmissionDatasetFactory($submission);
        $dataset = $datasetFactory->getDataset();

        if (empty($dataset->getFiles())) {
            return;
        }

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
            $userId = $request->getUser()->getId();
            $notificationContents = [
                'contents' => __(
                    'plugins.generic.dataverse.notification.error.depositFailed',
                    ['error' => $e->getMessage()]
                ),
            ];

            import('classes.notification.NotificationManager');
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification(
                $userId,
                NOTIFICATION_TYPE_ERROR,
                $notificationContents
            );
            error_log('Dataverse API error: ' . $e->getMessage());
        }

        $request = Application::get()->getRequest();
        $contextId = $request->getContext()->getId();
        $credentials = DAORegistry::getDAO('DataverseCredentialsDAO')->get($contextId);
        $swordAPIBaseUrl = $credentials->getDataverseServerUrl() . '/dvn/api/data-deposit/v1.1/swordv2/';

        $dataverseStudyDAO = DAORegistry::getDAO('DataverseStudyDAO');
        $study = $dataverseStudyDAO->newDataObject();
        $study->setSubmissionId($submission->getId());
        $study->setPersistentId($datasetIdentifier->getPersistentId());
        $study->setEditUri($swordAPIBaseUrl . 'edit/study/' . $datasetIdentifier->getPersistentId());
        $study->setEditMediaUri($swordAPIBaseUrl . 'edit-media/study/' . $datasetIdentifier->getPersistentId());
        $study->setStatementUri($swordAPIBaseUrl . 'statement/study/' . $datasetIdentifier->getPersistentId());
        $study->setPersistentUri('https://doi.org/' . str_replace('doi:', '', $datasetIdentifier->getPersistentId()));
        $dataverseStudyDAO->insertStudy($study);

        SubmissionLog::logEvent(
            $request,
            $submission,
            SUBMISSION_LOG_SUBMISSION_SUBMIT,
            'plugins.generic.dataverse.log.researchDataDeposited',
            ['persistendId' => $study->getPersistentId()]
        );

        DAORegistry::getDAO('DraftDatasetFileDAO')->deleteBySubmissionId($submission->getId());
    }
}
