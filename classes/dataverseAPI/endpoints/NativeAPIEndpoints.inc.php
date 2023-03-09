<?php

class NativeAPIEndpoints
{
    private $dataverseServerUrl;

    private $dataverseCollection;

    public function __construct(string $dataverseServerUrl, string $dataverseCollection)
    {
        $this->dataverseServerUrl = $dataverseServerUrl;
        $this->dataverseCollection = $dataverseCollection;
    }

    protected function getAPIBaseUrl(): string
    {
        return $this->dataverseServerUrl . '/api';
    }

    public function getDataverseServerEndpoint(): string
    {
        return $this->getAPIBaseUrl() . '/dataverses/' . ':root';
    }

    public function getDataverseCollectionEndpoint(): string
    {
        return $this->getAPIBaseUrl() . '/dataverses/' . $this->dataverseCollection;
    }

    public function getDatasetDataEndpoint(string $persistentId): string
    {
        return $this->getAPIBaseUrl() . '/datasets/:persistentId/versions/:latest/metadata/citation?persistentId=' . $persistentId;
    }

    public function getDatasetFilesEndpoint(string $persistentId): string
    {
        return $this->getAPIBaseUrl() . '/datasets/:persistentId/versions/:latest/files?persistentId=' . $persistentId;
    }

    public function getDatasetUpdateEndpoint(string $persistentId): string
    {
        return $this->getAPIBaseUrl() . '/datasets/:persistentId/editMetadata?persistentId=' . $persistentId . '&replace=true';
    }
}
