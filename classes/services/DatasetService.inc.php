<?php

import('plugins.generic.dataverse.dataverseAPI.DataverseClient');
import('plugins.generic.dataverse.classes.entities.Dataset');

class DatasetService
{
    public function deposit(int $submissionId, Dataset $dataset): void
    {
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
        $submission = Services::get('submission')->get($submissionId);

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

        SubmissionLog::logEvent(
            $request,
            $submission,
            SUBMISSION_LOG_SUBMISSION_SUBMIT,
            'plugins.generic.dataverse.log.researchDataDeposited',
            ['persistentId' => $study->getPersistentId()]
        );

        DAORegistry::getDAO('DraftDatasetFileDAO')->deleteBySubmissionId($submission->getId());
    }

    public function update(array $data): void
    {
        $dataset = new Dataset();
        $dataset->setAllData($data);

        try {
            $dataverseClient = new DataverseClient();
            $dataverseClient->getDatasetActions()->update($dataset);
        } catch (DataverseException $e) {
            error_log('Dataverse API error: ' . $e->getMessage());
        }

        $request = Application::get()->getRequest();
        $study = DAORegistry::getDAO('DataverseStudyDAO')->getByPersistentId($dataset->getPersistentId());
        $submission = Services::get('submission')->get($study->getSubmissionId());
        SubmissionLog::logEvent(
            $request,
            $submission,
            SUBMISSION_LOG_METADATA_UPDATE,
            'plugins.generic.dataverse.log.researchDataUpdated'
        );
    }

    public function delete(DataverseStudy $study): void
    {
        $dataverseClient = new DataverseClient();
        $dataverseClient->getDatasetActions()->delete($study->getPersistentId());

        DAORegistry::getDAO('DataverseStudyDAO')->deleteStudy($study);

        $request = Application::get()->getRequest();
        $submission = Services::get('submission')->get($study->getSubmissionId());
        SubmissionLog::logEvent(
            $request,
            $submission,
            SUBMISSION_LOG_METADATA_UPDATE,
            'plugins.generic.dataverse.log.researchDataDeleted'
        );
    }
}
