<?php

class DataverseServer
{
    private $credentials;

    private $dataverseServerUrl;

    private $dataverseCollection;

    public function __construct(DataverseCredentials $credentials, string $dataverseServerUrl, string $dataverseCollection)
    {
        $this->credentials = $credentials;
        $this->dataverseServerUrl = $dataverseServerUrl;
        $this->dataverseCollection = $dataverseCollection;
    }

    public function getCredentials(): DataverseCredentials
    {
        return $this->credentials;
    }

    public function getDataverseServerUrl(): string
    {
        return $this->dataverseServerUrl;
    }

    public function getDataverseCollection(): string
    {
        return $this->dataverseCollection;
    }
}
