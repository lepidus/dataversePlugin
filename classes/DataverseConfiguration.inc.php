<?php

define('DATAVERSE_API_VERSION', "v1.1");

class DataverseConfiguration
{
    private $apiToken;
    private $dataverseServer;
    private $dataverseUrl;
    private $dataverseCollection;

    public function __construct(string $apiToken, string $dataverseServer, string $dataverseUrl)
    {
        $this->apiToken = $apiToken;
        $this->dataverseServer = $dataverseServer;
        $this->dataverseUrl = $dataverseUrl;
        $this->dataverseCollection = $this->retrieveDataverseCollection();
    }

    public function getAPIToken(): string
    {
        return $this->apiToken;
    }
    
    public function getDataverseServer(): string
    {
        return $this->dataverseServer;
    }
    
    public function getDataverseUrl(): string
    {
        return $this->dataverseUrl;
    }

    private function retrieveDataverseCollection(): string
    {
        return explode($this->dataverseServer, $this->dataverseUrl)[1];
    }

    public function getDataverseCollection(): string
    {
        return $this->dataverseCollection;
    }

    public function getDataDepositBaseUrl(): string
    {
        return $this->getDataverseServer(). '/dvn/api/data-deposit/'. DATAVERSE_API_VERSION. '/swordv2/';
    }

    public function getDataverseServiceDocumentUrl(): string
    {
        return $this->getDataDepositBaseUrl(). 'service-document';
    }

    public function getDataverseDepositUrl(): string
    {
        return $this->getDataDepositBaseUrl(). 'collection'. $this->dataverseCollection;
    }

    public function getDataverseReleaseUrl(): string
    {
        return $this->getDataDepositBaseUrl(). 'edit'. $this->dataverseCollection;
    }

}
