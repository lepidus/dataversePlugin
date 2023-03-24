<?php

define('HTTP_STATUS_OK', 200);
define('HTTP_STATUS_CREATED', 201);

import('plugins.generic.dataverse.classes.dataverseAPI.response.DataverseAPIResponse');

abstract class NativeAPIOperations
{
    protected $serverURL;

    protected $apiToken;

    protected $collectionAlias;

    public function configure(DataverseCredentials $config): void
    {
        $this->serverUrl = $config->getDataverseServerUrl();
        $this->apiToken = $config->getApiToken();
        $this->collectionAlias = $config->getDataverseCollection();
    }

    public function createAPIURL(array $pathParams): string
    {
        return $this->getBaseAPIURL() . implode('/', $pathParams);
    }

    public function getBaseAPIURL(): string
    {
        return $this->serverUrl . '/api/';
    }

    protected function getHttpClient(): \GuzzleHttp\Client
    {
        return Application::get()->getHttpClient();
    }

    protected function getDataverseHeaders(): array
    {
        $headers = ['X-Dataverse-key' => $this->apiToken];
        return $headers;
    }

    protected function executeRequest(string $type, string $url, array $options): DataverseAPIResponse
    {
        try {
            $response = $this->getHttpClient()->request($type, $url, $options);
        } catch (GuzzleHttp\Exception\RequestException $e) {
            $responseMessage = $e->getMessage();
            if ($e->hasResponse()) {
                $responseMessage = $e->getResponse()->getBody(true) . ' (' . $e->getResponse()->getStatusCode() . ' ' . $e->getResponse()->getReasonPhrase() . ')';
            }

            return new DataverseAPIResponse(
                $e->getCode(),
                $responseMessage
            );
        }
        return new DataverseAPIResponse(
            $response->getStatusCode(),
            $response->getReasonPhrase(),
            $response->getBody(true)
        );
    }
}
