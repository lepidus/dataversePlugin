<?php

namespace APP\plugins\generic\dataverse\dataverseAPI\search;

use APP\plugins\generic\dataverse\classes\entities\DataverseResponse;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;

class DataverseSearchBuilder
{
    private const URL_MAX_LENGTH = 4096;

    private $configuration;
    private $httpClient;
    private $queries = [];
    private $types = [];
    private $filterQueries = [];

    public function __construct(DataverseConfiguration $configuration, \GuzzleHttp\Client $httpClient)
    {
        $this->configuration = $configuration;
        $this->httpClient = $httpClient;
    }

    private function getDataverseSearchEndpoint(): string
    {
        return $this->configuration->getDataverseServerUrl() . '/api/search';
    }

    public function addQuery(string $query): DataverseSearchBuilder
    {
        $this->queries[] = $query;
        return $this;
    }

    public function addType(string $type): DataverseSearchBuilder
    {
        $this->types[] = $type;
        return $this;
    }

    public function addFilterQuery(string $field, string $value): DataverseSearchBuilder
    {
        $this->filterQueries[] = [$field => $value];
        return $this;
    }

    private function escapeColon(string $value): string
    {
        return str_replace(':', '\:', $value);
    }

    public function getSearchUrls(): array
    {
        if (empty($this->queries)) {
            $this->addQuery('*');
        }

        $baseUrl = $this->getDataverseSearchEndpoint() . '?';
        $baseUrl .= 'q=' . implode('+', $this->queries);

        if (!empty($this->types)) {
            $baseUrl .= '&type=' . implode('&type=', $this->types);
        }

        if (empty($this->filterQueries)) {
            return [$baseUrl];
        }

        $filterParts = array_map(function (array $filterQuery) {
            $field = key($filterQuery);
            $value = $filterQuery[$field];
            return $field . ':' . $this->escapeColon($value);
        }, $this->filterQueries);

        $urls = [];
        for ($i = 0; $i < count($filterParts); $i = $j) {
            $currentUrl = $baseUrl . '&fq=' . $filterParts[$i];

            for ($j = $i + 1; $j < count($filterParts); $j++) {
                $nextFilterPart = '+' . $filterParts[$j];
                if ((strlen($currentUrl) + strlen($nextFilterPart)) > self::URL_MAX_LENGTH) {
                    break;
                }
                $currentUrl .= $nextFilterPart;
            }
            $urls[] = $currentUrl;
        }

        return $urls;
    }

    private function executeRequest(string $url): DataverseResponse
    {
        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'X-Dataverse-key' => $this->configuration->getAPIToken()
                ]
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
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
            $response->getStatusCode(),
            $response->getReasonPhrase(),
            $response->getBody()
        );
    }

    public function search(): array
    {
        $responses = [];
        foreach ($this->getSearchUrls() as $url) {
            $responses[] = $this->executeRequest($url);
        }
        return $responses;
    }

    public function count(): int
    {
        $total = 0;
        foreach ($this->search() as $response) {
            $body = json_decode($response->getBody(), true);
            if (isset($body['data']['total_count'])) {
                $total += $body['data']['total_count'];
            }
        }
        return $total;
    }
}
