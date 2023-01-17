<?php

class NewDataverseConfiguration
{
    private $credentials;

    private $dataverseCollection;

    public function __construct(int $contextId)
    {
        $credentialsDAO = DAORegistry::getDAO('DataverseCredentialsDAO');
        $credentials = $credentialsDAO->get($contextId);
        $this->dataverseCollection = $this->retrieveDataverseCollection();
    }

    public function getCredentials(): DataverseCredentials
    {
        return $this->credentials;
    }

    public function getDataverseServer(): string
    {
        preg_match('/https:\/\/(.)*?(?=\/)/', $this->credentials->getDataverseUrl(), $matches);
        return $matches[0];
    }

    public function getDataverseCollection(): string
    {
        return $this->dataverseCollection;
    }

    private function retrieveDataverseCollection(): string
    {
        return explode($this->getDataverseServer(), $this->credentials->getDataverseUrl())[1];
    }
}
