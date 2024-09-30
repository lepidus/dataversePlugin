<?php

namespace APP\plugins\generic\dataverse\dataverseAPI\actions;

use APP\core\Application;
use PKP\db\DAORegistry;
use PKP\cache\CacheManager;
use GuzzleHttp\Exception\TransferException;
use APP\plugins\generic\dataverse\classes\entities\DataverseResponse;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;

abstract class DataverseActions
{
    protected $contextId;
    protected $serverURL;
    protected $apiToken;
    protected $dataverseAlias;
    protected $client;
    protected $cacheManager;

    protected const ONE_DAY_SECONDS = 24 * 60 * 60;

    public function __construct(
        DataverseConfiguration $configuration = null,
        \GuzzleHttp\Client $client = null
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

    public function nativeAPIRequest(string $method, string $uri, array $options = [], bool $returnResponse = true): ?DataverseResponse
    {
        $options['headers']['X-Dataverse-key'] = $this->apiToken;

        try {
            $response = $this->client->request($method, $uri, $options);
        } catch (TransferException $e) {
            $message = $e->getMessage();
            $code = $e->getCode();

            if (method_exists($e, 'hasResponse') and $e->hasResponse()) {
                $response = $e->getResponse();
                $code = $response->getStatusCode();

                $responseBody = json_decode($response->getBody(), true);
                if (!empty($responseBody)) {
                    $message = $responseBody['message'];
                }
            }
            throw new DataverseException($message, $code, $e);
        }

        if (!$returnResponse) {
            return null;
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

        try {
            $response = $this->client->request($method, $uri, $options);
        } catch (TransferException $e) {
            $message = $e->getMessage();
            $code = $e->getCode();

            if (method_exists($e, 'hasResponse') and $e->hasResponse()) {
                $response = $e->getResponse();
                $code = $response->getStatusCode();
                $message = $response->getReasonPhrase();
            }
            throw new DataverseException($message, $code, $e);
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
}
