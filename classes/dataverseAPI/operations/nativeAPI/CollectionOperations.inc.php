<?php

import('plugins.generic.dataverse.classes.dataverseAPI.operations.nativeAPI.NativeAPIOperations');
import('plugins.generic.dataverse.classes.dataverseAPI.operations.nativeAPI.interfaces.CollectionOperationsInterface');

class CollectionOperations extends NativeAPIOperations implements CollectionOperationsInterface
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

        if ($response->getStatusCode() !== HTTP_STATUS_CREATED) {
            throw new Exception('Error creating dataset: ' . $response->getMessage());
        }

        return $response->getBodyAsEntity(DatasetIdentifier::class);
    }
}
