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

    public function getDataverseDepositUrl() {
        return $this->getDataverseServer(). '/dvn/api/data-deposit/'. DATAVERSE_API_VERSION. '/swordv2/collection'. $this->dataverseCollection;
    }    

    public function getDataverseCollection() {
        return $this->dataverseCollection;
    }

}