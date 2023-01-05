<?php

class NativeAPIEndpoints
{
    private $dataverseServerUrl;

    public function __construct(string $dataverseServerUrl)
    {
        $this->dataverseServerUrl = $dataverseServerUrl;
    }

    private function getAPIBaseUrl(): string
    {
        return $this->dataverseServerUrl . '/api/datasets/:persistentId';
    }

    public function getUpdateMetadataUrl(string $persistentId): string
    {
        return $this->getAPIBaseUrl() . '/editMetadata?persistentId=' . $persistentId . '&replace=true';
    }
}