<?php

class UpdateAPIService
{
    private $client;

    public function __construct(IUpdateAPIClient $client)
    {
        $this->client = $client;
    }

    public function updateDataset(Dataset $dataset): ?array
    {
        $packager = $this->client->getDatasetPackager($dataset);
        $packager->createDatasetPackage();
        $response = $this->client->updateDataset($dataset->getPersistentId(), $packager);

        if ($response->getStatusCode() !== 200) {
            throw new Exception($response->getMessage(), $response->getStatusCode());
        }

        return json_decode($response->getData(), true);
    }
}
