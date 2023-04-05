<?php

class NativeAPI
{
    private $serverUrl;
    private $apiToken;
    private $dataverseAlias;
    private $httpClient;

    public function __construct()
    {
        $contextId = Application::get()->getRequest()->getContext()->getId();
        $credentials = DAORegisty::getDAO('DataverseCredentialsDAO')->get($contextId);

        $this->serverUrl = $credentials->getDataverseServerUrl();
        $this->apiToken = $credentials->getAPIToken();
        $this->dataverseAlias = $credentials->getDataverseCollection();
        $this->httpClient = new HttpClient();
    }

    public function createURI(string ...$pathParams): string
    {
        return $this->serverUrl . '/api/' . join('/', $pathParams);
    }

    public function makeRequest(string $method, string $uri, array $options): array
    {
        $options['headers']['X-Dataverse-key'] = $this->apiToken;

        return $this->httpClient->request($method, $uri, $options);
    }
}
