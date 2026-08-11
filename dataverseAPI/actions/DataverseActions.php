<?php

namespace APP\plugins\generic\dataverse\dataverseAPI\actions;

use APP\core\Application;
use PKP\db\DAORegistry;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Support\Facades\Cache;
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

    protected const ONE_DAY_SECONDS = 24 * 60 * 60;
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
        $options += ['connect_timeout' => 5, 'timeout' => 15];

        try {
            $response = $this->client->request($method, $uri, $options);
        } catch (TransferException $e) {
            throw DataverseException::fromTransferException($e);
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
        $options += ['connect_timeout' => 5, 'timeout' => 15];

        try {
            $response = $this->client->request($method, $uri, $options);
        } catch (TransferException $e) {
            throw DataverseException::fromTransferException($e);
        }

        return new DataverseResponse(
            $response->getStatusCode(),
            $response->getReasonPhrase(),
            $response->getBody()
        );
    }
}
