<?php

import('plugins.generic.dataverse.classes.api.providers.NativeAPIDatasetProvider');
import('plugins.generic.dataverse.classes.api.endpoints.NativeAPIEndpoints');
import('plugins.generic.dataverse.classes.api.response.DataverseResponse');
import('plugins.generic.dataverse.classes.api.clients.interfaces.UpdateAPIClient');
import('plugins.generic.dataverse.classes.NewDataverseConfiguration');

class NativeAPIClient implements UpdateAPIClient
{
    private $configuration;
    private $httpClient;
    private $endpoints;

    public function __construct(int $contextId)
    {
        $this->configuration = new NewDataverseConfiguration($contextId);
        $this->httpClient = Application::get()->getHttpClient();
        $this->endpoints = new NativeAPIEndpoints($this->configuration->getDataverseServer());
    }

    public function newDatasetProvider(Submission $submission): DatasetProvider
    {
        return new NativeAPIDatasetProvider($submission);
    }

    public function updateDataset(DatasetProvider $datasetProvider, string $persistentId): DataverseResponse
    {
        $type = 'PUT';
        $url = $this->endpoints->getUpdateMetadataUrl($persistentId);
        $headers = $this->getDataverseHeaders(['Content-Type' => 'application/json']);
        $options = $this->getOptions($headers, $datasetProvider);

        return $this->executeRequest($type, $url, $options);
    }

    private function getDataverseHeaders(array $headers = []): array
    {
        $dataverseHeaders = ['X-Dataverse-key' => $this->configuration->getApiToken()];
        if (!empty($headers)) {
            array_merge($dataverseHeaders, $headers);
        }
        return $dataverseHeaders;
    }

    private function getOptions(array $headers, DatasetProvider $datasetProvider)
    {
        return [
            'headers' => $headers,
            'body' => GuzzleHttp\Psr7\Utils::tryFopen($datasetProvider->getDatasetPath(), 'r')
        ];
    }

    private function executeRequest(string $type, string $url, array $options): DataverseResponse
    {
        try {
            $response = $this->httpClient->request($type, $url, $options);
        } catch (GuzzleHttp\Exception\RequestException $e) {
            $returnMessage = $e->getMessage();
            $content = $e->hasResponse() ? $e->getResponse()->getBody(true) : null;
            return new DataverseResponse(
                $e->getResponse()->getStatusCode(),
                $content,
                $returnMessage
            );
        }
        return new DataverseResponse(
            $response->getStatusCode(),
            ['content' => $response->getBody()],
            $response->getReasonPhrase()
        );
    }
}
