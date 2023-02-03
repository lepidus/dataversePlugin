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
        $packager->createPackage();

        $response = $client->depositDataset($packager);

        if (
            $response->getStatusCode() >= 200
            && $response->getStatusCode() < 300
        ) {
            $study = $this->insertDataverseStudy(
                $submission->getId(),
                $response->getData()
            );
            return $study;
        } else {
            throw new Exception($response->getMessage(), $response->getStatusCode());
        }

        return null;
    }

    private function insertDataverseStudy(int $submissionId, string $responseData): DataverseStudy
    {
        $studyDAO = DAORegistry::getDAO('DataverseStudyDAO');

        $studyData = json_decode($responseData);

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
