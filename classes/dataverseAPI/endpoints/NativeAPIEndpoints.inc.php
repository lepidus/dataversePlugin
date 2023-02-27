<?php

import('plugins.generic.dataverse.classes.dataverseAPI.endpoints.DataverseEndpoints');

class NativeAPIEndpoints extends DataverseEndpoints
{
    protected function getAPIBaseUrl(): string
    {
        return $this->server->getDataverseServerUrl() . '/api';
    }

    public function getDataverseServerUrl(): string
    {
        return $this->getAPIBaseUrl() . '/dataverses/' . ':root';
    }

    public function getDataverseCollectionUrl(): string
    {
        return $this->getAPIBaseUrl() . '/dataverses/' . $this->server->getDataverseCollection();
    }

    public function getDatasetDataUrl(string $persistentId): string
    {
        return $this->getAPIBaseUrl() . '/datasets/:persistentId/versions/:latest/metadata/citation?persistentId=' . $persistentId;
    }
}
