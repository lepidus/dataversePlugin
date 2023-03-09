<?php

define('DATAVERSE_API_STATUS_OK', 200);

class DataAPIService
{
    private $client;

    public function __construct(IDataAPIClient $client)
    {
        $this->client = $client;
    }

    public function getDataverseServerName(): string
    {
        $response = $this->client->getDataverseServerData();

        if ($response->getStatusCode() != DATAVERSE_API_STATUS_OK) {
            throw new Exception($response->getMessage(), $response->getStatusCode());
        }

        $dataverseServerData = json_decode($response->getData())->data;

        return $dataverseServerData->name;
    }

    public function getDataverseCollectionName(): string
    {
        $response = $this->client->getDataverseCollectionData();

        if ($response->getStatusCode() != DATAVERSE_API_STATUS_OK) {
            throw new Exception($response->getMessage(), $response->getStatusCode());
        }

        $dataverseCollectionData = json_decode($response->getData())->data;

        return $dataverseCollectionData->name;
    }

    public function getDataset(string $persistentId): Dataset
    {
        $response = $this->client->getDatasetData($persistentId);

        if ($response->getStatusCode() != DATAVERSE_API_STATUS_OK) {
            throw new Exception($response->getMessage(), $response->getStatusCode());
        }

        return $this->client->getDatasetFactory($response)->getDataset();
    }

    public function getDatasetFiles(string $persistentId): array
    {
        $response = $this->client->getDatasetFilesData($persistentId);

        if ($response->getStatusCode() != DATAVERSE_API_STATUS_OK) {
            throw new Exception($response->getMessage(), $response->getStatusCode());
        }

        return $this->client->retrieveDatasetFiles($response->getData());
    }
}
