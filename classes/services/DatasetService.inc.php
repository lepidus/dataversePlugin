<?php

import('plugins.generic.dataverse.classes.services.DataverseService');
import('plugins.generic.dataverse.dataverseAPI.DataverseClient');
import('plugins.generic.dataverse.classes.entities.Dataset');

class DatasetService extends DataverseService
{
    public function deposit(int $submissionId, Dataset $dataset): void
    {
        if (empty($dataset->getFiles())) {
            return;
        }

        $request = Application::get()->getRequest();
        $contextId = $request->getContext()->getId();
        $submission = Services::get('submission')->get($submissionId);

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
            $this->registerAndNotifyError(
                $submission,
                'plugins.generic.dataverse.error.depositFailed',
                $e->getMessage()
            );
            return;
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
        $dataset = new Dataset();
        $dataset->setAllData($data);

        $study = DAORegistry::getDAO('DataverseStudyDAO')->getByPersistentId($dataset->getPersistentId());
        $submission = Services::get('submission')->get($study->getSubmissionId());

        try {
            $dataverseClient = new DataverseClient();
            $dataverseClient->getDatasetActions()->update($dataset);
        } catch (DataverseException $e) {
            $this->registerAndNotifyError(
                $submission,
                'plugins.generic.dataverse.error.updateFailed',
                $e->getMessage()
            );
            return;
        }

        $this->registerEventLog(
            $submission,
            'plugins.generic.dataverse.log.researchDataUpdated'
        );
    }

    public function delete(DataverseStudy $study): void
    {
        $submission = Services::get('submission')->get($study->getSubmissionId());

        try {
            $dataverseClient = new DataverseClient();
            $dataverseClient->getDatasetActions()->delete($study->getPersistentId());
        } catch (DataverseException $e) {
            $this->registerAndNotifyError(
                $submission,
                'plugins.generic.dataverse.error.deleteFailed',
                $e->getMessage()
            );
            return;
        }

        DAORegistry::getDAO('DataverseStudyDAO')->deleteStudy($study);

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
            $dataverseClient->getDatasetActions()->publish($study->getPersistentId());
        } catch (DataverseException $e) {
            $this->registerAndNotifyError(
                $submission,
                'plugins.generic.dataverse.error.publishFailed',
                $e->getMessage()
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
}
