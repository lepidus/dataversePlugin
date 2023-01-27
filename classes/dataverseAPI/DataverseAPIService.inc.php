<?php

class DataverseAPIService
{
    public function getDataset(string $persistentId, IDataAPIClient $client): ?Dataset
    {
        $response = $client->getDatasetData($persistentId);

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $factory = $client->getDatasetFactory($response);
            $dataset = $factory->getDataset();
            return $dataset;
        } else {
            throw new Exception($response->getMessage(), $response->getStatusCode());
        }

        return null;
    }
}
