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

    private function escapeColon(string $value): string
    {
        return str_replace(':', '\:', $value);
    }

    public function getSearchUrls(): array
    {
        if (empty($this->queries)) {
            $this->addQuery('*');
        }

        $searchUrl =  $this->getDataverseSearchEndpoint() . '?';
        $searchUrl .= 'q=' . implode('+', $this->queries);

        if (!empty($this->types)) {
            $searchUrl .= '&type=' .  implode('&type=', $this->types);
        }

        if (!empty($this->filterQueries)) {
            $searchUrl .= '&fq=' . implode('+', array_map(function (array $filterQuery) {
                $field = key($filterQuery);
                $value = $filterQuery[$field];
                return $field . ':' . $this->escapeColon($value);
            }, $this->filterQueries));
        }

        return [$searchUrl];
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

    // Refactor the current search request, turning it into a function that receives the request URL
    //   and executes it. Should be called 'executeRequest'.
    // Add a new function 'search' function to replace the current one. It should create n URL queries
    //   and execute all of them using the new 'executeRequest'. It should return an array of DataverseResponse's.
    // Add a new function called 'count'. It should work just as the search function, but instead of returning the
    //  responses, it should sum the 'total_count' of them all and return it.
}
