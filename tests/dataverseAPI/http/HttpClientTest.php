<?php

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Exception\RequestException;

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.dataverseAPI.http.HttpClient');
import('plugins.generic.dataverse.classes.entities.DataverseResponse');

class HttpClientTest extends PKPTestCase
{
    public function testSuccessfulRequest(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], '{"foo": "bar"}'),
        ]);
        $guzzleClient = new Client(['handler' => $mockHandler]);
        $httpClient = new HttpClient($guzzleClient);
        $response = $httpClient->request('GET', 'https://example.com');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"foo": "bar"}', $response->getBody());
    }

    public function testRequestErrorWithoutResponseThrownDataverseException(): void
    {
        $mockHandler = new MockHandler([
            new RequestException(
                'Error Communicating with Server',
                new Request('GET', 'test')
            )
        ]);
        $guzzleClient = new Client(['handler' => $mockHandler]);
        $httpClient = new HttpClient($guzzleClient);
        $this->expectException(DataverseException::class);
        $this->expectExceptionMessage('Error Communicating with Server');
        $httpClient->request('GET', 'test');
    }

    public function testRequestErrorWithResponseThrownDataverseException(): void
    {
        $mockHandler = new MockHandler([
            new RequestException(
                'Error Communicating with Server',
                new Request('GET', 'test'),
                new Response(400, [], '{"status":"ERROR", "message":"Bad Request"}')
            )
        ]);
        $guzzleClient = new Client(['handler' => $mockHandler]);
        $httpClient = new HttpClient($guzzleClient);
        $this->expectException(DataverseException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('Bad Request');
        $httpClient->request('GET', 'test');
    }

    public function testRequestErrorWithResponseBodyEmptyThrownDataverseException(): void
    {
        $mockHandler = new MockHandler([
            new RequestException(
                'Error Communicating with Server',
                new Request('GET', 'test'),
                new Response(500, [], '{}')
            )
        ]);
        $guzzleClient = new Client(['handler' => $mockHandler]);
        $httpClient = new HttpClient($guzzleClient);
        $this->expectException(DataverseException::class);
        $this->expectExceptionCode(500);
        $this->expectExceptionMessage('Error Communicating with Server');
        $httpClient->request('GET', 'test');
    }
}
