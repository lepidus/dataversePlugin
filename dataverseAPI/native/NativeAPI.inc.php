<?php

import('plugins.generic.dataverse.dataverseAPI.http.HttpClient');

class NativeAPI
{
    private $serverUrl;
    private $apiToken;
    private $dataverseAlias;
    private $httpClient;

    public function __construct()
    {
        $contextId = Application::get()->getRequest()->getContext()->getId();
        $credentials = DAORegistry::getDAO('DataverseCredentialsDAO')->get($contextId);

        $this->serverUrl = $credentials->getDataverseServerUrl();
        $this->apiToken = $credentials->getAPIToken();
        $this->dataverseAlias = $credentials->getDataverseCollection();

        $this->httpClient = new HttpClient();
    }

    public function createURI(string ...$pathParams): string
    {
        return $this->serverUrl . '/api/' . join('/', $pathParams);
    }

    public function getCurrentDataverseURI(): string
    {
        return $this->createURI('dataverses', $this->dataverseAlias);
    }

    public function getRootDataverseURI(): string
    {
        return $this->createURI('dataverses', ':root');
    }

    public function makeRequest(string $method, string $uri, array $options = []): DataverseResponse
    {
        $options['headers']['X-Dataverse-key'] = $this->apiToken;

        return $this->httpClient->request($method, $uri, $options);
    }
}
