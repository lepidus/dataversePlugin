<?php

import('plugins.generic.dataverse.classes.dataverseAPI.clients.interfaces.IDataAPIClient');
import('plugins.generic.dataverse.classes.dataverseAPI.clients.interfaces.IUpdateAPIClient');
import('plugins.generic.dataverse.classes.dataverseAPI.endpoints.NativeAPIEndpoints');
import('plugins.generic.dataverse.classes.factories.dataset.NativeAPIDatasetFactory');
import('plugins.generic.dataverse.classes.dataverseAPI.packagers.NativeAPIDatasetPackager');
import('plugins.generic.dataverse.classes.entities.DataverseResponse');

class NativeAPIClient implements IDataAPIClient, IUpdateAPIClient
{
    private $contextId;

    public function __construct(int $contextId)
    {
        $this->contextId = $contextId;
    }

    public function getCredentials(): DataverseCredentials
    {
        return DAORegistry::getDAO('DataverseCredentialsDAO')->get($this->contextId);
    }

    public function getAPIEndpoints(): NativeAPIEndpoints
    {
        $credentials = $this->getCredentials();
        return new NativeAPIEndpoints(
            $credentials->getDataverseServerUrl(),
            $credentials->getDataverseCollection()
        );
    }

    public function getHttpClient(): \GuzzleHttp\Client
    {
        return Application::get()->getHttpClient();
    }

    public function getDatasetPackager(Dataset $datataset): DatasetPackager
    {
        return new NativeAPIDatasetPackager($datataset);
    }

    public function getDatasetFactory(DataverseResponse $response): DatasetFactory
    {
        return new NativeAPIDatasetFactory($response);
    }

    public function getDataverseServerData(): DataverseResponse
    {
        $type = 'GET';
        $url = $this->getAPIEndpoints()->getDataverseServerEndpoint();
        $options = $this->getDataverseOptions();

        return $this->executeRequest($type, $url, $options);
    }

    public function getDataverseCollectionData(): DataverseResponse
    {
        $type = 'GET';
        $url = $this->getAPIEndpoints()->getDataverseCollectionEndpoint();
        $options = $this->getDataverseOptions();

        return $this->executeRequest($type, $url, $options);
    }

    public function getDatasetData(string $persistentId): DataverseResponse
    {
        $type = 'GET';
        $url = $this->getAPIEndpoints()->getDatasetDataEndpoint($persistentId);
        $options = $this->getDataverseOptions();

        return $this->executeRequest($type, $url, $options);
    }

    public function getDatasetFilesData(string $persistentId): DataverseResponse
    {
        $type = 'GET';
        $url = $this->getAPIEndpoints()->getDatasetFilesEndpoint($persistentId);
        $options = $this->getDataverseOptions();

        return $this->executeRequest($type, $url, $options);
    }

    public function retrieveDatasetFiles(string $fileData): array
    {
        import('plugins.generic.dataverse.classes.entities.DatasetFile');
        return array_map(function (stdClass $file) {
            $datasetFile = new DatasetFile();
            $datasetFile->setId($file->dataFile->id);
            $datasetFile->setTitle($file->label);
            return $datasetFile;
        }, json_decode($fileData)->data);
    }

    public function updateDataset(string $persistentId, DatasetPackager $packager): DataverseResponse
    {
        $type = 'PUT';
        $url = $this->getAPIEndpoints()->getDatasetUpdateEndpoint($persistentId);
        $options = $this->getDataverseOptions(
            ['Content-Type' => 'application/json'],
            ['body' => GuzzleHttp\Psr7\Utils::tryFopen($packager->getPackagePath(), 'rb')]
        );

        return $this->executeRequest($type, $url, $options);
    }

    public function getDataverseOptions(array $headers = [], array $options = []): array
    {
        $dataverseHeaders = ['headers' => $this->getDataverseHeaders($headers)];
        return array_merge($dataverseHeaders, $options);
    }

    private function getDataverseHeaders(array $headers = []): array
    {
        $apiToken = $this->getCredentials()->getAPIToken();
        $dataverseHeaders = ['X-Dataverse-key' => $apiToken];
        $headers = array_merge($dataverseHeaders, $headers);
        return $headers;
    }

    private function executeRequest(string $type, string $url, array $options): DataverseResponse
    {
        try {
            $response = $this->getHttpClient()->request($type, $url, $options);
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
