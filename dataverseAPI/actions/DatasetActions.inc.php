<?php

import('plugins.generic.dataverse.dataverseAPI.actions.interfaces.DatasetActionsInterface');
import('plugins.generic.dataverse.dataverseAPI.native.NativeAPI');
import('plugins.generic.dataverse.dataverseAPI.packagers.NativeAPIDatasetPackager');
import('plugins.generic.dataverse.classes.factories.JsonDatasetFactory');
import('plugins.generic.dataverse.classes.entities.DatasetIdentifier');

class DatasetActions implements DatasetActionsInterface
{
    public function get(string $persistendId): Dataset
    {
        $nativeAPI = new NativeAPI();
        $uri = $nativeAPI->createUri('datasets', ':persistentId?persistentId=' . $persistendId);
        $response = $nativeAPI->makeRequest('GET', $uri);

        $datasetFactory = new JsonDatasetFactory($response->getBody());
        return $datasetFactory->getDataset();
    }

    public function create(Dataset $dataset): DatasetIdentifier
    {
        $packager = new NativeAPIDatasetPackager($dataset);
        $packager->createDatasetPackage();

        $nativeAPI = new NativeAPI();
        $uri = $nativeAPI->getCurrentDataverseURI() . '/datasets';
        $options = [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => GuzzleHttp\Psr7\Utils::tryFopen($packager->getPackagePath(), 'rb')
        ];
        $response = $nativeAPI->makeRequest('POST', $uri, $options);

        $jsonContent = json_decode($response->getBody(), true);
        $datasetIdentifier = new DatasetIdentifier();
        $datasetIdentifier->setAllData($jsonContent['data']);
        $packager->clear();

        return $datasetIdentifier;
    }

    public function update(Dataset $dataset): void
    {
        $packager = new NativeAPIDatasetPackager($dataset);
        $packager->createDatasetPackage();

        $nativeAPI = new NativeAPI();
        $args = '?persistentId=' . $dataset->getPersistentId() . '&replace=true';
        $uri = $nativeAPI->createUri('datasets', ':persistentId', 'editMetadata', $args);
        $options = [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => GuzzleHttp\Psr7\Utils::tryFopen($packager->getPackagePath(), 'rb')
        ];
        $nativeAPI->makeRequest('PUT', $uri, $options);
        $packager->clear();
    }

    public function delete(string $persistendId): void
    {
        $nativeAPI = new NativeAPI();
        $uri = $nativeAPI->createUri('datasets', ':persistentId', 'versions', ':draft?persistentId=' . $persistendId);
        $nativeAPI->makeRequest('DELETE', $uri);
    }

    public function publish(string $persistendId): void
    {
        $nativeAPI = new NativeAPI();
        $args = '?persistentId=' . $persistendId . '&type=major';
        $uri = $nativeAPI->createUri('datasets', ':persistentId', 'actions', ':publish' . $args);

        $nativeAPI->makeRequest('POST', $uri);
    }
}
