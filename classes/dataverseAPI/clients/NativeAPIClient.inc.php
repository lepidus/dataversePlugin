<?php

class NativeAPIClient extends IDataAPIClient
{
    private $installation;

    private $endpoints;

    private $httpClient;

    public function __construct(int $contextId)
    {
        $this->installation = new DataverseInstallation($contextId);
        $this->endpoints = new NativeAPIEndpoints($this->installation);
        $this->httpClient = Application::get()->getHttpClient();
    }

    public function getDataverseData(): array
    {
        $type = 'GET';
        $url = $this->endpoints->getDataverseCollectionUrl();
        $options = $this->getDataverseOptions();

        return $this->executeRequest($type, $url, $options);
    }

    public function getDatasetData(string $persistentId): array
    {
        $type = 'GET';
        $url = $this->endpoints->getDataverseDatasetUrl($persistentId);
        $options = $this->getDataverseOptions();

        return $this->executeRequest($type, $url, $options);
    }

    public function getDataverseOptions(array $headers = [], array $options = []): array
    {
        return [
            'headers' => $this->getDataverseHeaders($headers),
            $options
        ];
    }

    private function getDataverseHeaders(array $headers = []): array
    {
        $apiToken = $this->installation->getCredentials()->getAPIToken();
        $dataverseHeaders = ['X-Dataverse-key' => $apiToken];
        array_merge($dataverseHeaders, $headers);
        return $dataverseHeaders;
    }

    private function executeRequest(string $type, string $url, array $options): array
    {
        try {
            $response = $this->httpClient->request($type, $url, $options);
            return [
              'status' => $response->getStatusCode(),
              'content' => $response->getBody()
            ];
        } catch (GuzzleHttp\Exception\RequestException $e) {
            return [
                'status' => $e->getResponse()->getStatusCode(),
                'message' => $e->getMessage()
            ];
        }
    }
}
