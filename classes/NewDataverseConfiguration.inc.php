<?php

class NewDataverseConfiguration
{
    private $apiToken;
    private $dataverseUrl;
    private $termsOfUse;
    private $dataverseCollection;

    public function __construct(int $contextId)
    {
        $dataverseDAO = DAORegistry::getDAO('DataverseDAO');
        $credentials = $dataverseDAO->getCredentialsFromDatabase($contextId);
        list($this->apiToken, $this->dataverseUrl, $this->termsOfUse) = $credentials;
        $this->dataverseCollection = $this->retrieveDataverseCollection();
    }

    public function getAPIToken(): string
    {
        return $this->apiToken;
    }

    public function getDataverseUrl(): string
    {
        return $this->dataverseUrl;
    }
    
    public function getTermsOfUse(): array
    {
        return $this->termsOfUse;
    }
    
    public function getDataverseServer(): string
    {
        preg_match('/https:\/\/(.)*?(?=\/)/', $this->dataverseUrl, $matches);
        return $matches[0];
    }

    public function getDataverseCollection(): string
    {
        return $this->dataverseCollection;
    }
    
    private function retrieveDataverseCollection(): string
    {
        return explode($this->getDataverseServer(), $this->dataverseUrl)[1];
    }

}
