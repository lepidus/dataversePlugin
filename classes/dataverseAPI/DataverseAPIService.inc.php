<?php

class DataverseAPIService
{
    public function getDataset(string $persistentId, IDataAPIClient $client): ?Dataset
    {
        $response = $client->getDatasetData($persistentId);

        if (
            $response->getStatusCode() >= 200
            && $response->getStatusCode() < 300
        ) {
            $factory = $client->getDatasetFactory($response);
            $dataset = $factory->getDataset();
            return $dataset;
        } else {
            throw new Exception($response->getMessage(), $response->getStatusCode());
        }

        return null;
    }

    public function depositDataset(SubmissionAdapter $submission, IDepositAPIClient $client): ?DataverseStudy
    {
        $factory = new SubmissionDatasetFactory($submission);
        $dataset = $factory->getDataset();

        if (empty($dataset->getFiles())) {
            return null;
        }

        $packager = $client->getDatasetPackager($dataset);
        $packager->createDatasetPackage();
        $packager->createFilesPackage();

        $depositResponse = $client->depositDataset($packager);
        if ($depositResponse->getStatusCode() > 300) {
            throw new Exception($depositResponse->getMessage(), $depositResponse->getStatusCode());
            return null;
        }

        $study = $this->insertDataverseStudy(
            $submission->getId(),
            $depositResponse->getData()
        );

        $filesDepositResponse = $client->depositDatasetFiles($study->getPersistentId(), $packager);
        if ($depositResponse->getStatusCode() > 300) {
            throw new Exception($depositResponse->getMessage(), $depositResponse->getStatusCode());
            return null;
        }

        return $study;
    }

    private function insertDataverseStudy(int $submissionId, string $responseData): DataverseStudy
    {
        $studyData = json_decode($responseData);

        $studyDAO = DAORegistry::getDAO('DataverseStudyDAO');
        $study = $studyDAO->newDataObject();
        $study->setSubmissionId($submissionId);
        $study->setEditUri($studyData->editUri);
        $study->setEditMediaUri($studyData->editMediaUri);
        $study->setStatementUri($studyData->statementUri);
        $study->setPersistentUri($studyData->persistentUri);
        $study->setPersistentId($studyData->persistentId);
        $studyId = $studyDAO->insertStudy($study);
        $study->setId($studyId);

        return $study;
    }
}
