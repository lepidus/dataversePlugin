<?php

define('DATAVERSE_API_VERSION', "v1.1");

class DataverseConfiguration
{
    private $apiToken;
    private $dataverseServer;
    private $dataverseUrl;
    private $dataverseCollection;

    public function __construct(String $apiToken, String $dataverseServer, String $dataverseUrl)
    {
        $this->apiToken = $apiToken;
        $this->dataverseServer = $dataverseServer;
        $this->dataverseUrl = $dataverseUrl;
        $this->dataverseCollection = $this->retrieveDataverseCollection();
    }

    public function getAPIToken(): String
    {
        return $this->apiToken;
    }
    
    public function getDataverseServer(): String
    {
        return $this->dataverseServer;
    }
    
    public function getDataverseUrl(): String
    {
        return $this->dataverseUrl;
    }

    private function retrieveDataverseCollection(): String
    {
        return explode($this->dataverseServer, $this->dataverseUrl)[1];
    }

    public function getDataverseCollection(): String
    {
        return $this->dataverseCollection;
    }

    public function getDataDepositBaseUrl(): String
    {
        return $this->getDataverseServer(). '/dvn/api/data-deposit/'. DATAVERSE_API_VERSION. '/swordv2/';
    }

    public function getDataverseServiceDocumentUrl(): String
    {
        return $this->getDataDepositBaseUrl(). 'service-document';
    }

    public function getDataverseDepositUrl(): String
    {
        return $this->getDataDepositBaseUrl(). 'collection'. $this->dataverseCollection;
    }

    public function getDataverseReleaseUrl(): String
    {
        return $this->getDataDepositBaseUrl(). 'edit'. $this->dataverseCollection;
    }

}