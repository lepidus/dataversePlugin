<?php

import('plugins.generic.dataverse.classes.dataverseAPI.endpoints.DataverseEndpoints');

class NativeAPIEndpoints extends DataverseEndpoints
{
    protected function getAPIBaseUrl(): string
    {
        return $this->server->getDataverseServerUrl() . '/api';
    }

    public function getDataverseServerEndpoint(): string
    {
        return $this->getAPIBaseUrl() . '/dataverses/' . ':root';
    }

    public function getDataverseCollectionEndpoint(): string
    {
        return $this->getAPIBaseUrl() . '/dataverses/' . $this->server->getDataverseCollection();
    }

    public function getDatasetDataEndpoint(string $persistentId): string
    {
        return $this->getAPIBaseUrl() . '/datasets/:persistentId/versions/:latest/metadata/citation?persistentId=' . $persistentId;
    }

    public function getDatasetFilesEndpoint(string $persistentId): string
    {
        return $this->getAPIBaseUrl() . '/datasets/:persistentId/versions/:latest/files?persistentId=' . $persistentId;
    }
}
