<?php

class NewDataverseService
{
    public function createDatasetInDataverse(DepositAPIClient $client, Submission $submission): void
    {
        $datasetProvider = $client->newDatasetProvider($submission);
        $files = $datasetProvider->getSubmissionFiles();

        if (!empty($files)) {
            $datasetProvider->createDataset();
            $datasetProvider->prepareDatasetFiles($files);

            $response = $client->depositDataset($datasetProvider);
            $responseData = $response->getContent();

            $this->insertDataverseStudy($submission, $responseData);
            $client->depositDatasetFiles($responseData['persistentId'], $datasetProvider);
        }
    }

    private function insertDataverseStudy(Submission $submission, array $responseData): void
    {
        $studyDAO = DAORegistry::getDAO('DataverseStudyDAO');

        $study = $studyDAO->newDataObject();
        $study->setSubmissionId($submission->getId());
        $study->setEditUri($responseData['editUri']);
        $study->setEditMediaUri($responseData['editMediaUri']);
        $study->setStatementUri($responseData['statementUri']);
        $study->setPersistentUri($responseData['persistentUri']);
        $study->setPersistentId($responseData['persistentId']);

        $studyDAO->insertStudy($study);
    }
}
