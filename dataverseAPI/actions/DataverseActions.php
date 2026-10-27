<?php

namespace APP\plugins\generic\dataverse\dataverseAPI\actions;

use APP\core\Application;
use PKP\config\Config;
use PKP\db\DAORegistry;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Support\Facades\Cache;
use APP\plugins\generic\dataverse\classes\entities\DataverseResponse;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;

abstract class DataverseActions
{
    protected $contextId;
    protected $configuration;
    protected $serverURL;
    protected $apiToken;
    protected $dataverseAlias;
    protected $client;

    protected const ONE_DAY_SECONDS = 24 * 60 * 60;
    private const DEFAULT_REQUEST_TIMEOUTS = ['read' => 15, 'create' => 30, 'upload' => 60, 'write' => 30];
    private const MIN_REQUEST_TIMEOUT = 1;
    private const MAX_REQUEST_TIMEOUT = 300;

    public function __construct(
        ?DataverseConfiguration $configuration = null,
        ?\GuzzleHttp\Client $client = null,
        ?int $contextId = null
    ) {
        $this->contextId = $contextId;

        if (is_null($configuration)) {
            $this->contextId ??= Application::get()->getRequest()->getContext()->getId();
            $configuration = DAORegistry::getDAO('DataverseConfigurationDAO')->get($this->contextId);
        }

        if (is_null($client)) {
            $client = Application::get()->getHttpClient();
        }

        $this->configuration = $configuration;
        $this->serverURL = $configuration->getDataverseServerUrl();
        $this->apiToken = $configuration->getAPIToken();
        $this->dataverseAlias = $configuration->getDataverseCollection();
        $this->client = $client;
    }

    protected function createDataverseClient(): DataverseClient
    {
        return new DataverseClient($this->configuration, $this->contextId, $this->client);
    }

    public static function getCacheKey(string $cacheId, int $contextId): string
    {
        return $cacheId . '_' . $contextId;
    }

    protected function getCached(string $cacheId)
    {
        return is_null($this->contextId)
            ? null
            : Cache::get(self::getCacheKey($cacheId, $this->contextId));
    }

    protected function putCached(string $cacheId, $contents): void
    {
        if (is_null($this->contextId)) {
            return;
        }

        Cache::put(self::getCacheKey($cacheId, $this->contextId), $contents, self::ONE_DAY_SECONDS);
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

    public function nativeAPIRequest(string $method, string $uri, array $options = [], bool $returnResponse = true): ?DataverseResponse
    {
        $options['headers']['X-Dataverse-key'] = $this->apiToken;
        $response = $this->sendRequest($method, $uri, $options);

        if (!$returnResponse) {
            return null;
        }

        return $response;
    }

    public function swordAPIRequest(string $method, string $uri, array $options = []): DataverseResponse
    {
        $options['auth'] = [$this->apiToken, ''];

        return $this->sendRequest($method, $uri, $options);
    }

    private function sendRequest(string $method, string $uri, array $options): DataverseResponse
    {
        $operation = $this->getRequestOperation($method, $uri);
        $options += ['connect_timeout' => 5, 'timeout' => $this->getRequestTimeout($operation)];

        try {
            $response = $this->client->request($method, $uri, $options);
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

        return new DataverseResponse(
            $response->getStatusCode(),
            $response->getReasonPhrase(),
            $response->getBody()
        );
    }

    private function getRequestOperation(string $method, string $uri): string
    {
        $path = (string) parse_url($uri, PHP_URL_PATH);

        if ($method === 'POST' && str_ends_with($path, '/add')) {
            return 'upload';
        }

        if ($method === 'POST' && str_ends_with($path, '/datasets')) {
            return 'create';
        }

        return $method === 'GET' ? 'read' : 'write';
    }

    private function getRequestTimeout(string $operation): int
    {
        $timeout = filter_var(
            Config::getVar('dataverse', $operation . '_timeout'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => self::MIN_REQUEST_TIMEOUT, 'max_range' => self::MAX_REQUEST_TIMEOUT]]
        );

        return $timeout === false ? self::DEFAULT_REQUEST_TIMEOUTS[$operation] : $timeout;
    }
}
