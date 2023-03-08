<?php

class DepositAPIService
{
    private $client;

    public function __construct(IDepositAPIClient $client)
    {
        $this->client = $client;
    }

    public function depositDataset(Dataset $dataset): ?array
    {
        if (empty($dataset->getFiles())) {
            return null;
        }

        $packager = $this->client->getDatasetPackager($dataset);
        $packager->createDatasetPackage();
        $packager->createFilesPackage();

        $datasetDepositResponse = $this->client->depositDataset($packager);
        if ($datasetDepositResponse->getStatusCode() > 300) {
            throw new Exception($datasetDepositResponse->getMessage(), $datasetDepositResponse->getStatusCode());
        }

        $datasetData = json_decode($datasetDepositResponse->getData(), true);

        $filesDepositResponse = $this->client->depositDatasetFiles($datasetData['persistentId'], $packager);
        if ($filesDepositResponse->getStatusCode() > 300) {
            throw new Exception($filesDepositResponse->getMessage(), $filesDepositResponse->getStatusCode());
        }

        return $datasetData;
    }
}
