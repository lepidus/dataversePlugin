<?php

class SWORDAPIEndpoints
{
    private const DATAVERSE_API_VERSION = 'v1.1';

    private $dataverseServerUrl;

    private $dataverseCollection;

    public function __construct(string $dataverseServerUrl, string $dataverseCollection)
    {
        $this->dataverseServerUrl = $dataverseServerUrl;
        $this->dataverseCollection = $dataverseCollection;
    }

    protected function getAPIBaseUrl(): string
    {
        return $this->dataverseServerUrl . '/dvn/api/data-deposit/' . self::DATAVERSE_API_VERSION . '/swordv2';
    }

    public function getDataverseServiceDocumentUrl(): string
    {
        return $this->getAPIBaseUrl() . '/service-document';
    }

    public function getDataverseCollectionUrl(): string
    {
        return $this->getAPIBaseUrl() . '/collection/dataverse/' . $this->dataverseCollection;
    }

    public function getDataverseEditUrl(): string
    {
        return $this->getAPIBaseUrl() . '/edit/dataverse/' . $this->dataverseCollection;
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
