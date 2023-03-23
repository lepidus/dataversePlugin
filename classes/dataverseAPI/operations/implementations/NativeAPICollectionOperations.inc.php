<?php

import('plugins.generic.dataverse.classes.dataverseAPI.operations.NativeAPIDataverseOperations');
import('plugins.generic.dataverse.classes.dataverseAPI.operations.interfaces.CollectionOperationsInterface');

class NativeAPICollectionOperations extends NativeAPIDataverseOperations implements CollectionOperationsInterface
{
    public function createDataset(string $datasetPackagePath): DatasetIdentifier
    {
        $apiURL = $this->createAPIURL(['dataverses', $this->collectionAlias, 'datasets']);
        $requestType = 'POST';
        $headers = array_merge(
            $this->getDataverseHeaders(),
            ['Content-Type' => 'application/json']
        );
        $options = [
            'headers' => $headers,
            'body' => GuzzleHttp\Psr7\Utils::tryFopen($datasetPackagePath, 'rb')
        ];

        $response = $this->executeRequest($requestType, $apiURL, $options);

        if ($response->getStatusCode() !== HTTP_STATUS_OK) {
            throw new Exception('Error creating dataset: ' . $response->getMessage());
        }

        return $this->retrieveDatasetIdentifier($response->getData());
    }

    public function retrieveDatasetIdentifier(string $responseData): DatasetIdentifier
    {
        $datasetData = json_decode($responseData, true)['data'];
        $datasetIdentifier = new DatasetIdentifier(
            $datasetData['id'],
            $datasetData['persistentId']
        );
        return $datasetIdentifier;
    }
}
