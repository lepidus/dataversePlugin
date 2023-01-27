<?php

import('plugins.generic.dataverse.classes.dataverseAPI.endpoints.DataverseEndpoints');

class NativeAPIEndpoints extends DataverseEndpoints
{
    protected function getAPIBaseUrl(): string
    {
        return $this->installation->getDataverseServerUrl() . '/api';
    }

    public function getDataverseCollectionUrl(): string
    {
        return $this->getAPIBaseUrl() . '/dataverses/' . $this->installation->getDataverseCollection();
    }

    public function getDatasetDataUrl(string $persistentId): string
    {
        return $this->getAPIBaseUrl() . '/datasets/export?exporter=dataverse_json&persistentId=' . $persistentId;
    }
}
