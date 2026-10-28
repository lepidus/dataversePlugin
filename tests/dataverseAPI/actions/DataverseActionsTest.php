<?php

use GuzzleHttp\Client;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use PKP\config\Config;
use PKP\tests\PKPTestCase;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DataverseActions;
use APP\plugins\generic\dataverse\classes\entities\DataverseResponse;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;

class DataverseActionsTest extends PKPTestCase
{
    private $configuration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configuration = new DataverseConfiguration();
        $this->configuration->setDataverseUrl('https://test.dataverse.org/dataverses/testDataverse');
        $this->configuration->setAPIToken('apiToken');
    }

    public function testNativeApiUriCreation(): void
    {
        $actions = new ConcreteDataverseActions($this->configuration, new Client());

        $encodedDoi = urlencode('doi:10.12345/FK2/123456');
        $uri = $actions->createNativeAPIURI(['datasets', ':persistentId'], ['persistentId' => 'doi:10.12345/FK2/123456']);
        $this->assertEquals(
            "https://test.dataverse.org/api/datasets/:persistentId?persistentId=$encodedDoi",
            $uri
        );
        $uri = $actions->createNativeAPIURI(
            ['datasets', 'export'],
            ['exporter' => 'dataverse_json', 'persistentId' => 'doi:10.12345/FK2/123456']
        );
        $this->assertEquals(
            "https://test.dataverse.org/api/datasets/export?exporter=dataverse_json&persistentId=$encodedDoi",
            $uri
        );
    }

    public function testGetCurrentDataverseUri(): void
    {
        $actions = new ConcreteDataverseActions($this->configuration, new Client());

        $uri = $actions->getCurrentDataverseURI();

        $this->assertEquals(
            'https://test.dataverse.org/api/dataverses/testDataverse',
            $uri
        );
    }

    public function testGetRootDataverseUri(): void
    {
        $actions = new ConcreteDataverseActions($this->configuration, new Client());

        $uri = $actions->getRootDataverseURI();

        $this->assertEquals(
            'https://test.dataverse.org/api/dataverses/:root',
            $uri
        );
    }

    public function testSwordApiUriCreation(): void
    {
        $actions = new ConcreteDataverseActions($this->configuration, new Client());

        $uri = $actions->createSWORDAPIURI('edit', 'file', '12345');

        $this->assertEquals(
            'https://test.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit/file/12345',
            $uri
        );
    }

    public function testSuccessfulNativeApiRequest(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], '{"foo": "bar"}'),
        ]);
        $guzzleClient = new Client(['handler' => $mockHandler]);

        $actions = new ConcreteDataverseActions($this->configuration, $guzzleClient);

        $response = $actions->nativeAPIRequest('GET', 'https://example.com');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"foo": "bar"}', $response->getBody());
    }

    public function testRequestErrorWithoutResponseThrowsDataverseException(): void
    {
        $mockHandler = new MockHandler([
            new RequestException(
                'Error Communicating with Server',
                new Request('GET', 'test')
            )
        ]);
        $guzzleClient = new Client(['handler' => $mockHandler]);

        $actions = new ConcreteDataverseActions($this->configuration, $guzzleClient);

        $this->expectException(DataverseException::class);
        $this->expectExceptionCode(503);
        $this->expectExceptionMessage(__('plugins.generic.dataverse.error.exception.unavailable'));
        $actions->nativeAPIRequest('GET', 'test');
    }

    public function testConnectionErrorThrowsDataverseException(): void
    {
        $mockHandler = new MockHandler([
            new ConnectException(
                'Failed to connect to Dataverse',
                new Request('GET', 'test')
            )
        ]);
        $guzzleClient = new Client(['handler' => $mockHandler]);

        $actions = new ConcreteDataverseActions($this->configuration, $guzzleClient);

        $this->expectException(DataverseException::class);
        $this->expectExceptionCode(503);
        $this->expectExceptionMessage(__('plugins.generic.dataverse.error.exception.unavailable'));
        $actions->nativeAPIRequest('GET', 'test');
    }

    public function testRequestErrorWithResponseThrowsDataverseException(): void
    {
        $mockHandler = new MockHandler([
            new RequestException(
                'Error Communicating with Server',
                new Request('GET', 'test'),
                new Response(400, [], '{"status":"ERROR", "message":"Bad Request"}')
            )
        ]);
        $guzzleClient = new Client(['handler' => $mockHandler]);

        $actions = new ConcreteDataverseActions($this->configuration, $guzzleClient);

        $this->expectException(DataverseException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('Bad Request');
        $actions->nativeAPIRequest('GET', 'test');
    }

    public function testRequestErrorWithResponseBodyEmptyThrowsDataverseException(): void
    {
        $mockHandler = new MockHandler([
            new RequestException(
                'Error Communicating with Server',
                new Request('GET', 'test'),
                new Response(500, [], '{}')
            )
        ]);
        $guzzleClient = new Client(['handler' => $mockHandler]);

        $actions = new ConcreteDataverseActions($this->configuration, $guzzleClient);

        $this->expectException(DataverseException::class);
        $this->expectExceptionCode(503);
        $this->expectExceptionMessage(__('plugins.generic.dataverse.error.exception.unavailable'));
        $actions->nativeAPIRequest('GET', 'test');
    }

    public function testHtmlChallengeResponseIsReportedAsServiceUnavailableWithoutLeakingBody(): void
    {
        $mockHandler = new MockHandler([
            new RequestException(
                '403 Forbidden',
                new Request('GET', 'test'),
                new Response(
                    403,
                    ['Content-Type' => 'text/html', 'cdn-challenge' => 'true'],
                    '<html><title>Establishing a secure connection ...</title></html>'
                )
            )
        ]);
        $guzzleClient = new Client(['handler' => $mockHandler]);

        $actions = new ConcreteDataverseActions($this->configuration, $guzzleClient);

        try {
            $actions->nativeAPIRequest('GET', 'test');
            $this->fail('A DataverseException was expected');
        } catch (DataverseException $exception) {
            $this->assertSame(503, $exception->getCode());
            $this->assertSame(__('plugins.generic.dataverse.error.exception.unavailable'), $exception->getMessage());
            $this->assertStringNotContainsString('Establishing a secure connection', $exception->getMessage());
        }
    }

    public function testJsonAuthenticationErrorIsReportedAsInvalidOrExpiredToken(): void
    {
        $mockHandler = new MockHandler([
            new RequestException(
                '403 Forbidden',
                new Request('GET', 'test'),
                new Response(403, ['Content-Type' => 'application/json'], '{"status":"ERROR","message":"Bad API key"}')
            )
        ]);
        $guzzleClient = new Client(['handler' => $mockHandler]);

        $actions = new ConcreteDataverseActions($this->configuration, $guzzleClient);

        try {
            $actions->nativeAPIRequest('GET', 'test');
            $this->fail('A DataverseException was expected');
        } catch (DataverseException $exception) {
            $this->assertSame(401, $exception->getCode());
            $this->assertSame(__('plugins.generic.dataverse.error.exception.invalidToken'), $exception->getMessage());
            $this->assertSame('plugins.generic.dataverse.error.invalidToken', $exception->getUserMessageKey());
            $this->assertStringNotContainsString('Bad API key', $exception->getMessage());
        }
    }

    public function testUnauthorizedJsonResponseIsReportedAsInvalidOrExpiredToken(): void
    {
        $mockHandler = new MockHandler([
            new RequestException(
                '401 Unauthorized',
                new Request('GET', 'test'),
                new Response(401, ['Content-Type' => 'application/json'], '{"status":"ERROR","message":"Bad API key"}')
            )
        ]);
        $guzzleClient = new Client(['handler' => $mockHandler]);

        $actions = new ConcreteDataverseActions($this->configuration, $guzzleClient);

        try {
            $actions->nativeAPIRequest('GET', 'test');
            $this->fail('A DataverseException was expected');
        } catch (DataverseException $exception) {
            $this->assertSame(401, $exception->getCode());
            $this->assertSame('plugins.generic.dataverse.error.invalidToken', $exception->getUserMessageKey());
        }
    }

    public function testJsonPermissionErrorIsNotReportedAsInvalidToken(): void
    {
        $mockHandler = new MockHandler([
            new RequestException(
                '403 Forbidden',
                new Request('GET', 'test'),
                new Response(403, ['Content-Type' => 'application/json'], '{"status":"ERROR","message":"User is not permitted"}')
            )
        ]);
        $guzzleClient = new Client(['handler' => $mockHandler]);

        $actions = new ConcreteDataverseActions($this->configuration, $guzzleClient);

        try {
            $actions->nativeAPIRequest('GET', 'test');
            $this->fail('A DataverseException was expected');
        } catch (DataverseException $exception) {
            $this->assertSame(403, $exception->getCode());
            $this->assertSame('User is not permitted', $exception->getUserMessage());
            $this->assertNotSame(__('plugins.generic.dataverse.error.exception.invalidToken'), $exception->getMessage());
        }
    }

    public function testJsonValidationErrorKeepsMessageReportedByDataverse(): void
    {
        $mockHandler = new MockHandler([
            new RequestException(
                '403 Forbidden',
                new Request('POST', 'test'),
                new Response(403, ['Content-Type' => 'application/json'], '{"status":"ERROR","message":"Validation Failed: Subject is required."}')
            )
        ]);
        $guzzleClient = new Client(['handler' => $mockHandler]);

        $actions = new ConcreteDataverseActions($this->configuration, $guzzleClient);

        try {
            $actions->nativeAPIRequest('POST', 'test');
            $this->fail('A DataverseException was expected');
        } catch (DataverseException $exception) {
            $this->assertSame(403, $exception->getCode());
            $this->assertSame('Validation Failed: Subject is required.', $exception->getUserMessage());
        }
    }

    public function testRequestErrorWithoutResponseIsReportedAsServiceUnavailable(): void
    {
        $mockHandler = new MockHandler([
            new ConnectException(
                'Connection timed out with infrastructure details',
                new Request('GET', 'test')
            )
        ]);
        $guzzleClient = new Client(['handler' => $mockHandler]);

        $actions = new ConcreteDataverseActions($this->configuration, $guzzleClient);

        try {
            $actions->nativeAPIRequest('GET', 'test');
            $this->fail('A DataverseException was expected');
        } catch (DataverseException $exception) {
            $this->assertSame(503, $exception->getCode());
            $this->assertSame(__('plugins.generic.dataverse.error.exception.unavailable'), $exception->getMessage());
            $this->assertSame(__('plugins.generic.dataverse.error.unavailable'), $exception->getUserMessage());
        }
    }

    public function testRequestTimeoutDependsOnTheOperation(): void
    {
        $observedTimeouts = [];
        $actions = new ConcreteDataverseActions($this->configuration, $this->createTimeoutObservingClient($observedTimeouts));

        $actions->nativeAPIRequest('GET', 'https://example.com/api/datasets/1');
        $actions->nativeAPIRequest('GET', 'https://example.com/api/access/datafile/1', [], false);
        $actions->nativeAPIRequest('POST', 'https://example.com/api/dataverses/test/datasets');
        $actions->nativeAPIRequest('POST', 'https://example.com/api/datasets/:persistentId/add?persistentId=doi:10.1/X');
        $actions->nativeAPIRequest('DELETE', 'https://example.com/api/datasets/1');
        $actions->swordAPIRequest('DELETE', 'https://example.com/dvn/api/data-deposit/v1.1/swordv2/edit-media/file/1');
        $actions->nativeAPIRequest('POST', 'https://example.com/api/datasets/1/add', ['timeout' => 42]);

        $this->assertSame([[5, 15], [5, 60], [5, 30], [5, 60], [5, 30], [5, 30], [5, 42]], $observedTimeouts);
    }

    public function testConfiguredRequestTimeoutOutsideTheAllowedRangeFallsBackToTheDefault(): void
    {
        $config = &Config::getData();
        $originalDataverseConfig = $config['dataverse'] ?? null;
        $observedTimeouts = [];
        $actions = new ConcreteDataverseActions($this->configuration, $this->createTimeoutObservingClient($observedTimeouts));

        try {
            foreach ([0, 301, 1.5, true, 'invalid', null, 90] as $uploadTimeout) {
                $config['dataverse']['upload_timeout'] = $uploadTimeout;
                $actions->nativeAPIRequest('POST', 'https://example.com/api/datasets/1/add');
            }
        } finally {
            if (is_null($originalDataverseConfig)) {
                unset($config['dataverse']);
            } else {
                $config['dataverse'] = $originalDataverseConfig;
            }
        }

        $this->assertSame([60, 60, 60, 60, 60, 60, 90], array_column($observedTimeouts, 1));
    }

    public function testFailureCategoryDistinguishesTimeoutConnectionAuthenticationAndHttpErrors(): void
    {
        $request = new Request('POST', 'https://example.com');
        $timeout = DataverseException::fromTransferException(
            new ConnectException('sensitive transport detail', $request, null, ['errno' => 28])
        );
        $connection = DataverseException::fromTransferException(new ConnectException('refused', $request));
        $authentication = DataverseException::fromTransferException(
            new RequestException('unauthorized', $request, new Response(401, [], '{"message": "Bad API key"}'))
        );
        $http = DataverseException::fromTransferException(
            new RequestException('bad request', $request, new Response(400, [], '{"message": "Invalid field"}'))
        );

        $this->assertSame('timeout', $timeout->getFailureCategory());
        $this->assertStringNotContainsString('sensitive', $timeout->getMessage());
        $this->assertSame('connection', $connection->getFailureCategory());
        $this->assertSame('authentication', $authentication->getFailureCategory());
        $this->assertSame('http', $http->getFailureCategory());
    }

    public function testResponseBodyIsNotReadWhenTheResponseIsNotReturned(): void
    {
        $sinkPath = tempnam(sys_get_temp_dir(), 'datasetFile');
        $sink = Utils::tryFopen($sinkPath, 'w');
        $client = new Client(['handler' => function () use ($sink) {
            return new FulfilledPromise(new Response(200, [], Utils::streamFor($sink)));
        }]);
        $actions = new ConcreteDataverseActions($this->configuration, $client);

        try {
            $response = $actions->nativeAPIRequest(
                'GET',
                'https://example.com/api/access/datafile/1',
                ['sink' => $sink],
                false
            );
        } finally {
            unlink($sinkPath);
        }

        $this->assertNull($response);
    }

    public function testFailureLogHasTheStatusReturnedByDataverse(): void
    {
        $logPath = tempnam(sys_get_temp_dir(), 'dataverseLog');
        $errorLog = ini_set('error_log', $logPath);
        $request = new Request('GET', 'https://example.com/api/datasets/1');
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(504),
            new ConnectException('refused', $request),
        ]))]);
        $actions = new ConcreteDataverseActions($this->configuration, $client);

        try {
            foreach ([1, 2] as $attempt) {
                try {
                    $actions->nativeAPIRequest('GET', 'https://example.com/api/datasets/1');
                } catch (DataverseException $exception) {
                }
            }
            $log = file_get_contents($logPath);
        } finally {
            ini_set('error_log', $errorLog);
            unlink($logPath);
        }

        $this->assertStringContainsString('"operation":"read","category":"http","status":504', $log);
        $this->assertStringContainsString('"operation":"read","category":"connection","status":null', $log);
    }

    private function createTimeoutObservingClient(array &$observedTimeouts): Client
    {
        return new Client(['handler' => function ($request, array $options) use (&$observedTimeouts) {
            $observedTimeouts[] = [$options['connect_timeout'], $options['timeout']];
            return new FulfilledPromise(new Response(200, [], '{}'));
        }]);
    }
}

class ConcreteDataverseActions extends DataverseActions
{
}
