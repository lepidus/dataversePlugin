<?php

class DataverseConfiguration
{
    private $apiToken;
    private $dataverseServer;
    private $dataverseUrl;

    public function __construct(String $apiToken, String $dataverseServer, String $dataverseUrl)
    {
        $this->apiToken = $apiToken;
        $this->dataverseServer = $dataverseServer;
        $this->dataverseUrl = $dataverseUrl;
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
}