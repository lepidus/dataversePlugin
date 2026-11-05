<?php

use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

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
    protected const UNAVAILABLE_MESSAGE = 'plugins.generic.dataverse.error.unavailable';
    protected const UNAVAILABLE_STATUS_CODE = 503;

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

    public function nativeAPIRequest(
        string $method,
        string $uri,
        array $options = [],
        bool $returnResponse = true
    ): ?DataverseResponse {
        $options['headers']['X-Dataverse-key'] = $this->apiToken;
        $operation = $this->getRequestOperation($method, $uri);
        $options += ['connect_timeout' => 5, 'timeout' => $this->getRequestTimeout($operation)];

        $response = $this->sendRequest($method, $uri, $options, $operation);

        if (!$returnResponse) {
            return null;
        }

        return new DataverseResponse(
            $response->getStatusCode(),
            $response->getReasonPhrase(),
            $response->getBody()
        );
    }

    private function refuseRedirectOutsideServer(): callable
    {
        return function (RequestInterface $request, ResponseInterface $response, UriInterface $redirectUri): void {
            if ($this->isServerOrigin($redirectUri)) {
                return;
            }

            error_log(json_encode([
                'component' => 'dataverse',
                'category' => 'redirect_refused',
                'redirectHost' => $redirectUri->getHost(),
            ]));

            throw new DataverseException(__(self::UNAVAILABLE_MESSAGE), self::UNAVAILABLE_STATUS_CODE);
        };
    }

    protected function isServerOrigin(UriInterface $uri): bool
    {
        $serverUri = new Uri($this->serverURL);

        return $serverUri->getScheme() === $uri->getScheme()
            && $serverUri->getAuthority() === $uri->getAuthority();
    }

    protected function sendRequest(string $method, string $uri, array $options, string $operation): ResponseInterface
    {
        $options += ['allow_redirects' => ['strict' => true, 'on_redirect' => $this->refuseRedirectOutsideServer()]];

        try {
            return $this->client->request($method, $uri, $options);
        } catch (TransferException $e) {
            $exception = DataverseException::fromTransferException($e);
            error_log(json_encode([
                'component' => 'dataverse',
                'operation' => $operation,
                'category' => $exception->getFailureCategory(),
                'status' => $exception->getCode(),
            ]));
            throw $exception;
        }
    }

    public function swordAPIRequest(string $method, string $uri, array $options = []): DataverseResponse
    {
        $options['auth'] = [$this->apiToken, ''];
        $operation = $this->getRequestOperation($method, $uri);
        $options += ['connect_timeout' => 5, 'timeout' => $this->getRequestTimeout($operation)];

        $response = $this->sendRequest($method, $uri, $options, $operation);

        return new DataverseResponse(
            $response->getStatusCode(),
            $response->getReasonPhrase(),
            $response->getBody()
        );
    }

    private function getRequestOperation(string $method, string $uri): string
    {
        $path = (string) parse_url($uri, PHP_URL_PATH);
        if ($method === 'POST' && substr($path, -4) === '/add') {
            return 'upload';
        }
        if ($method === 'POST' && substr($path, -9) === '/datasets') {
            return 'create';
        }
        if ($method === 'GET' && strpos($path, '/access/datafile/') !== false) {
            return 'download';
        }

        return $method === 'GET' ? 'read' : 'write';
    }

    protected function getRequestTimeout(string $operation): int
    {
        $defaults = ['read' => 15, 'create' => 30, 'upload' => 60, 'write' => 30, 'download' => 300];
        $configured = Config::getVar('dataverse', $operation . '_timeout');
        $timeout = filter_var($configured, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 300]]);
        if ($timeout !== false) {
            return $timeout;
        }
        return $defaults[$operation];
    }

    public function cacheDismiss()
    {
        return null;
    }

}
