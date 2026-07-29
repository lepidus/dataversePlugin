<?php

use GuzzleHttp\Exception\TransferException;

import('plugins.generic.dataverse.classes.entities.DataverseResponse');
import('plugins.generic.dataverse.classes.exception.DataverseException');

abstract class DataverseActions
{
    protected $contextId;
    protected $serverURL;
    protected $apiToken;
    protected $dataverseAlias;
    protected $client;
    protected $cacheManager;

    protected const ONE_DAY_SECONDS = 24 * 60 * 60;
    private const SERVICE_UNAVAILABLE_MESSAGE = 'Dataverse service is temporarily unavailable.';
    private const SERVICE_UNAVAILABLE_STATUS = 503;

    public function __construct(
        ?DataverseConfiguration $configuration = null,
        ?\GuzzleHttp\Client $client = null
    ) {
        if (is_null($configuration)) {
            $this->contextId = Application::get()->getRequest()->getContext()->getId();
            $configuration = DAORegistry::getDAO('DataverseConfigurationDAO')->get($this->contextId);
        }

        if (is_null($client)) {
            $client = Application::get()->getHttpClient();
        }

        $this->serverURL = $configuration->getDataverseServerUrl();
        $this->apiToken = $configuration->getAPIToken();
        $this->dataverseAlias = $configuration->getDataverseCollection();
        $this->client = $client;
        $this->cacheManager = CacheManager::getManager();
    }

    public function createNativeAPIURI(array $pathParams, array $queryParams = []): string
    {
        $uri = $this->serverURL . '/api/' . join('/', $pathParams);
        if (!empty($queryParams)) {
            $uri .= '?' . http_build_query($queryParams);
        }

        return $uri;
    }

    public function createSWORDAPIURI(string ...$pathParams): string
    {
        return $this->serverURL . '/dvn/api/data-deposit/v1.1/swordv2/' . join('/', $pathParams);
    }

    public function getCurrentDataverseURI(): string
    {
        return $this->createNativeAPIURI(['dataverses', $this->dataverseAlias]);
    }

    public function getRootDataverseURI(): string
    {
        return $this->createNativeAPIURI(['dataverses', ':root']);
    }

    public function nativeAPIRequest(string $method, string $uri, array $options = []): DataverseResponse
    {
        $options['headers']['X-Dataverse-key'] = $this->apiToken;
        $options += ['connect_timeout' => 5, 'timeout' => 15];

        try {
            $response = $this->client->request($method, $uri, $options);
        } catch (TransferException $e) {
            throw $this->createDataverseException($e);
        }

        return new DataverseResponse(
            $response->getStatusCode(),
            $response->getReasonPhrase(),
            $response->getBody()
        );
    }

    public function swordAPIRequest(string $method, string $uri, array $options = []): DataverseResponse
    {
        $options['auth'] = [$this->apiToken, ''];
        $options += ['connect_timeout' => 5, 'timeout' => 15];

        try {
            $response = $this->client->request($method, $uri, $options);
        } catch (TransferException $e) {
            throw $this->createDataverseException($e);
        }

        return new DataverseResponse(
            $response->getStatusCode(),
            $response->getReasonPhrase(),
            $response->getBody()
        );
    }

    public function cacheDismiss()
    {
        return null;
    }

    private function createDataverseException(TransferException $exception): DataverseException
    {
        if (!method_exists($exception, 'hasResponse') || !$exception->hasResponse()) {
            return new DataverseException(
                self::SERVICE_UNAVAILABLE_MESSAGE,
                self::SERVICE_UNAVAILABLE_STATUS,
                $exception
            );
        }

        $response = $exception->getResponse();
        $statusCode = $response->getStatusCode();
        $message = $this->getJsonErrorMessage($response);

        if ($message !== null && in_array($statusCode, [400, 404, 409, 422], true)) {
            return new DataverseException($message, $statusCode, $exception);
        }

        return new DataverseException(
            self::SERVICE_UNAVAILABLE_MESSAGE,
            self::SERVICE_UNAVAILABLE_STATUS,
            $exception
        );
    }

    private function getJsonErrorMessage($response): ?string
    {
        $responseBody = json_decode((string) $response->getBody(), true);
        if (!is_array($responseBody) || !isset($responseBody['message']) || !is_string($responseBody['message'])) {
            return null;
        }

        return $responseBody['message'];
    }
}
