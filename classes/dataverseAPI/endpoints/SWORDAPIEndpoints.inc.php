<?php

import('plugins.generic.dataverse.classes.dataverseAPI.endpoints.DataverseEndpoints');

class SWORDAPIEndpoints extends DataverseEndpoints
{
    private const DATAVERSE_API_VERSION = 'v1.1';

    protected function getAPIBaseUrl(): string
    {
        return $this->installation->getDataverseServerUrl() . '/dvn/api/data-deposit/' . self::DATAVERSE_API_VERSION . '/swordv2';
    }

    public function getDataverseServiceDocumentUrl(): string
    {
        return $this->getAPIBaseUrl() . '/service-document';
    }

    public function getDataverseCollectionUrl(): string
    {
        return $this->getAPIBaseUrl() . '/collection/dataverse/' . $this->installation->getDataverseCollection();
    }

    public function getDataverseEditUrl(): string
    {
        return $this->getAPIBaseUrl() . '/edit/dataverse/' . $this->installation->getDataverseCollection();
    }

    public function getDatasetEditUrl(string $persistentId): string
    {
        return $this->getAPIBaseUrl() . '/edit/study/' . $persistentId;
    }

    public function getDatasetEditMediaUrl(string $persistentId): string
    {
        return $this->getAPIBaseUrl() . '/edit-media/study/' . $persistentId;
    }

    public function getDatasetStatementUrl(string $persistentId): string
    {
        return $this->getAPIBaseUrl() . '/statement/study/' . $persistentId;
    }

    public function getDatasetFileUrl(int $fileId): string
    {
        return $this->getAPIBaseUrl() . '/edit-media/file/' . $fileId;
    }
}
