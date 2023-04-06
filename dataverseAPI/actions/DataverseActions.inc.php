<?php

use GuzzleHttp\Exception\RequestException;

import('plugins.generic.dataverse.classes.entities.DataverseResponse');
import('plugins.generic.dataverse.classes.exception.DataverseException');

abstract class DataverseActions
{
    protected $serverURL;

    protected $apiToken;

    protected $dataverseAlias;

    protected $client;

    public function __construct(
        DataverseCredentials $credentials = null,
        \GuzzleHttp\Client $client = null
    ) {
        if (is_null($credentials)) {
            $contextId = Application::get()->getRequest()->getContext()->getId();
            $credentials = DAORegistry::getDAO('DataverseCredentialsDAO')->get($contextId);
        }

        if (is_null($client)) {
            $client = Application::get()->getHttpClient();
        }

        $this->serverURL = $credentials->getDataverseServerUrl();
        $this->apiToken = $credentials->getAPIToken();
        $this->dataverseAlias = $credentials->getDataverseCollection();
        $this->client = $client;
    }

    public function createNativeAPIURI(string ...$pathParams): string
    {
        return $this->serverURL . '/api/' . join('/', $pathParams);
    }

    public function createSWORDAPIURI(string ...$pathParams): string
    {
        return $this->serverURL . '/dvn/api/data-deposit/v1.1/swordv2/' .join('/', $pathParams);
    }

    public function getCurrentDataverseURI(): string
    {
        return $this->createNativeAPIURI('dataverses', $this->dataverseAlias);
    }

    public function getRootDataverseURI(): string
    {
        return $this->createNativeAPIURI('dataverses', ':root');
    }

    public function nativeAPIRequest(string $method, string $uri, array $options = []): DataverseResponse
    {
        $options['headers']['X-Dataverse-key'] = $this->apiToken;

        try {
            $reponse = $this->client->request($method, $uri, $options);
        } catch (RequestException $e) {
            $message = $e->getMessage();
            $code = $e->getCode();

            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $code = $response->getStatusCode();

                $responseBody = json_decode($response->getBody(), true);
                if (!empty($responseBody)) {
                    $message = $responseBody['message'];
                }
            }
            throw new DataverseException($message, $code, $e);
        }

        return new DataverseResponse(
            $reponse->getStatusCode(),
            $reponse->getReasonPhrase(),
            $reponse->getBody()
        );
    }

    public function swordAPIRequest(string $method, string $uri, array $options = []): DataverseResponse
    {
        $options['auth'] = [$this->apiToken, ''];

        try {
            $reponse = $this->client->request($method, $uri, $options);
        } catch (RequestException $e) {
            throw new DataverseException($e->getMessage(), $e->getCode(), $e);
        }

        return new DataverseResponse(
            $reponse->getStatusCode(),
            $reponse->getReasonPhrase(),
            $reponse->getBody()
        );
    }
}
