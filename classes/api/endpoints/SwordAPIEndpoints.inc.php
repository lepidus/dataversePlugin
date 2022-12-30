<?php

class SwordAPIEndpoints
{
    private const DATAVERSE_API_VERSION = 'v1.1';

    private $dataverseServerUrl;
    private $dataverseCollection;

    public function __construct(string $dataverseServerUrl, string $dataverseCollection)
    {
        $this->dataverseServerUrl = $dataverseServerUrl;
        $this->dataverseCollection = $dataverseCollection;
    }
    

    private function getAPIBaseUrl(): string
    {
        return $this->dataverseServerUrl . '/dvn/api/data-deposit/' . self::DATAVERSE_API_VERSION . '/swordv2';
    }

    public function getDataverseServiceDocumentUrl(): string
    {
        return $this->getAPIBaseUrl() . '/service-document';
    }

    public function getDataverseDepositUrl(): string
    {
        return $this->getAPIBaseUrl() . '/collection' . $this->dataverseCollection;
    }

    public function getDataverseReleaseUrl(): string
    {
        return $this->getAPIBaseUrl() . '/edit' . $this->dataverseCollection;
    }

    public function getEditUri(string $persistentId): string
    {
        return $this->getAPIBaseUrl() . '/edit/study/' . $persistentId;
    }

    public function getEditMediaUri(string $persistentId): string
    {
        return $this->getAPIBaseUrl() . '/edit-media/study/' . $persistentId;
    }

    public function getStatementUri(string $persistentId): string
    {
        return $this->getAPIBaseUrl() . '/statement/study/' . $persistentId;
    }

    public function getDatasetFileUri(int $fileId): string
    {
        return $this->getAPIBaseUrl() . '/edit-media/file/' . $fileId;
    }
}