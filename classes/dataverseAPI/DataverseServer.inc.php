<?php

class DataverseServer
{
    private $credentials;

    public function __construct(int $contextId)
    {
        $this->credentials = DAORegistry::getDAO('DataverseCredentialsDAO')->get($contextId);
    }

    public function getCredentials(): DataverseCredentials
    {
        return $this->credentials;
    }

    public function getDataverseServerUrl(): string
    {
        preg_match('/https:\/\/(.)*?(?=\/)/', $this->credentials->getDataverseUrl(), $matches);
        return $matches[0];
    }

    public function getDataverseCollection(): string
    {
        return $this->retrieveDataverseCollection();
    }

    private function retrieveDataverseCollection(): string
    {
        $explodedUrl = explode('/', $this->credentials->getDataverseUrl());
        return end($explodedUrl);
    }
}
