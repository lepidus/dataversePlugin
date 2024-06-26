<?php

namespace APP\plugins\generic\dataverse\dataverseAPI\search;

use APP\plugins\generic\dataverse\classes\entities\DataverseResponse;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;

class DataverseSearchBuilder
{
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

    public function getParams(): string
    {
        if (empty($this->queries)) {
            $this->addQuery('*');
        }

        $search = 'q=' . implode('+', $this->queries);

        if (!empty($this->types)) {
            $search .= '&type=' .  implode('&type=', $this->types);
        }

        if (!empty($this->filterQueries)) {
            $search .= '&fq=' . implode('+', array_map(function (array $filterQuery) {
                $field = key($filterQuery);
                $value = $filterQuery[$field];
                return $field . ':' . $this->escapeColon($value);
            }, $this->filterQueries));
        }

        return $search;
    }

    private function escapeColon(string $value): string
    {
        return str_replace(':', '\:', $value);
    }

    public function getSearchUrl(): string
    {
        return $this->getDataverseSearchEndpoint() . '?' . $this->getParams();
    }

    public function search(): DataverseResponse
    {
        try {
            $response = $this->httpClient->request('GET', $this->getSearchUrl(), [
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
}
