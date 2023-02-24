<?php

class DepositAPIService
{
    private $client;

    public function __construct(IDepositAPIClient $client)
    {
        $this->client = $client;
    }

    public function depositDataset(SubmissionAdapter $submission): ?DataverseStudy
    {
        $factory = new SubmissionDatasetFactory($submission);
        $dataset = $factory->getDataset();

        if (empty($dataset->getFiles())) {
            return null;
        }

        $packager = $this->client->getDatasetPackager($dataset);
        $packager->createDatasetPackage();
        $packager->createFilesPackage();

        $depositResponse = $this->client->depositDataset($packager);
        if ($depositResponse->getStatusCode() > 300) {
            throw new Exception($depositResponse->getMessage(), $depositResponse->getStatusCode());
            return null;
        }

        $study = $this->insertDataverseStudy(
            $submission->getId(),
            $depositResponse->getData()
        );

        $filesDepositResponse = $this->client->depositDatasetFiles($study->getPersistentId(), $packager);
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
