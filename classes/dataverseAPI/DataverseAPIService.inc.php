<?php

class DataverseAPIService
{
    public function getDataset(string $persistentId, IDataAPIClient $client): ?Dataset
    {
        $response = $client->getDatasetData($persistentId);

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $factory = new ResponseDatasetFactory($response->getBody());
            $dataset = $factory->getDataset();
            return $dataset;
        }

        return null;
    }
}
