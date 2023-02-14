<?php

import('plugins.generic.dataverse.classes.dataverseAPI.clients.interfaces.IDataAPIClient');
import('plugins.generic.dataverse.classes.dataverseAPI.endpoints.NativeAPIEndpoints');
import('plugins.generic.dataverse.classes.factories.dataset.NativeAPIDatasetFactory');
import('plugins.generic.dataverse.classes.entities.DataverseResponse');

class NativeAPIClient implements IDataAPIClient
{
    private $server;

    private $endpoints;

    private $httpClient;

    public function __construct(DataverseServer $server)
    {
        $this->httpClient = Application::get()->getHttpClient();
        $this->endpoints = new NativeAPIEndpoints($server);
        $this->server = $server;
    }

    public function getDatasetFactory(DataverseResponse $response): DatasetFactory
    {
        return new NativeAPIDatasetFactory($response);
    }

    public function getDataverseServerData(): DataverseResponse
    {
        $type = 'GET';
        $url = $this->endpoints->getDataverseServerUrl();
        $options = $this->getDataverseOptions();

        return $this->executeRequest($type, $url, $options);
    }

    public function getDataverseCollectionData(): DataverseResponse
    {
        $type = 'GET';
        $url = $this->endpoints->getDataverseCollectionUrl();
        $options = $this->getDataverseOptions();

        return $this->executeRequest($type, $url, $options);
    }

    public function getDatasetData(string $persistentId): DataverseResponse
    {
        $type = 'GET';
        $url = $this->endpoints->getDatasetDataUrl($persistentId);
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
        $apiToken = $this->server->getCredentials()->getAPIToken();
        $dataverseHeaders = ['X-Dataverse-key' => $apiToken];
        array_merge($dataverseHeaders, $headers);
        return $dataverseHeaders;
    }

    private function executeRequest(string $type, string $url, array $options): DataverseResponse
    {
        try {
            $response = $this->httpClient->request($type, $url, $options);
            return new DataverseResponse(
                $response->getStatusCode(),
                $response->getReasonPhrase(),
                $response->getBody(true)
            );
        } catch (GuzzleHttp\Exception\RequestException $e) {
            $responseMessage = $e->getMessage();
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $responseBody = json_decode($response->getBody(true));
                $responseMessage = $responseBody->status .
                    ': ' . $responseBody->message .
                    ' (' . $response->getStatusCode() . ' ' . $response->getReasonPhrase() . ')';
            }

            return new DataverseResponse(
                $e->getCode(),
                $responseMessage
            );
        }
    }
}
