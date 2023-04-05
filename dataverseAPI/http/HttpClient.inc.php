<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

import('plugins.generic.dataverse.classes.entities.DataverseResponse');
import('plugins.generic.dataverse.classes.exception.DataverseException');

class HttpClient
{
    public function __construct(Client $client = null)
    {
        $this->client = $client ?? Application::get()->getHttpClient();
    }

    public function request(string $method, string $uri, array $options = []): DataverseResponse
    {
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
}
