<?php

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.dataverseAPI.actions.DataverseActions');
import('plugins.generic.dataverse.classes.entities.DataverseResponse');
import('plugins.generic.dataverse.classes.dataverseConfiguration.DataverseConfiguration');

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

    public function testNativeAPIURICreation(): void
    {
        $actions = $this->getMockBuilder(DataverseActions::class)
            ->setConstructorArgs(array($this->configuration))
            ->getMockForAbstractClass();

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

    public function testGetCurrentDataverseURI(): void
    {
        $actions = $this->getMockBuilder(DataverseActions::class)
            ->setConstructorArgs(array($this->configuration))
            ->getMockForAbstractClass();

        $uri = $actions->getCurrentDataverseURI();

        $this->assertEquals(
            'https://test.dataverse.org/api/dataverses/testDataverse',
            $uri
        );
    }

    public function testGetRootDataverseURI(): void
    {
        $actions = $this->getMockBuilder(DataverseActions::class)
            ->setConstructorArgs(array($this->configuration))
            ->getMockForAbstractClass();

        $uri = $actions->getRootDataverseURI();

        $this->assertEquals(
            'https://test.dataverse.org/api/dataverses/:root',
            $uri
        );
    }

    public function testSwordAPIURICreation(): void
    {
        $actions = $this->getMockBuilder(DataverseActions::class)
            ->setConstructorArgs(array($this->configuration))
            ->getMockForAbstractClass();

        $uri = $actions->createSWORDAPIURI('edit', 'file', '12345');

        $this->assertEquals(
            'https://test.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit/file/12345',
            $uri
        );
    }

    public function testTimeoutsDependOnOperationAndPreserveExplicitOverride(): void
    {
        $observed = [];
        $client = new Client(['handler' => function ($request, $options) use (&$observed) {
            $observed[] = [$options['connect_timeout'], $options['timeout']];
            return new \GuzzleHttp\Promise\FulfilledPromise(new Response(200, [], '{}'));
        }]);
        $actions = $this->getMockBuilder(DataverseActions::class)
            ->setConstructorArgs([$this->configuration, $client])
            ->getMockForAbstractClass();
        $actions->nativeAPIRequest('GET', 'https://example.com/api/datasets/1');
        $actions->nativeAPIRequest('POST', 'https://example.com/api/dataverses/test/datasets');
        $actions->nativeAPIRequest('POST', 'https://example.com/api/datasets/:persistentId/add?persistentId=doi:10.1/X');
        $actions->nativeAPIRequest('DELETE', 'https://example.com/api/datasets/1');
        $actions->nativeAPIRequest('POST', 'https://example.com/api/datasets/1/add', ['timeout' => 42]);
        $this->assertSame([[5, 15], [5, 30], [5, 60], [5, 30], [5, 42]], $observed);
    }

    public function testConfiguredTimeoutRejectsInvalidBudgets(): void
    {
        $config = & Config::getData();
        $original = $config['dataverse'] ?? null;
        $observed = [];
        try {
            $client = new Client(['handler' => function ($request, $options) use (&$observed) {
                $observed[] = $options['timeout'];
                return new \GuzzleHttp\Promise\FulfilledPromise(new Response(200, [], '{}'));
            }]);
            $actions = $this->getMockBuilder(DataverseActions::class)
                ->setConstructorArgs([$this->configuration, $client])
                ->getMockForAbstractClass();
            foreach (['0', '301', '1.5', 'invalid', '90'] as $budget) {
                $config['dataverse']['upload_timeout'] = $budget;
                $actions->nativeAPIRequest('POST', 'https://example.com/api/datasets/1/add');
            }
            $this->assertSame([60, 60, 60, 60, 90], $observed);
        } finally {
            if ($original === null) {
                unset($config['dataverse']);
            } else {
                $config['dataverse'] = $original;
            }
        }
    }

    public function testTimeoutCategoryPreservesCauseWithoutExposingRequestSecrets(): void
    {
        $cause = new ConnectException('sensitive transport detail', new Request('POST', 'https://example.com'), null, ['errno' => 28]);
        $exception = DataverseException::fromTransferException($cause);
        $this->assertSame('timeout', $exception->getFailureCategory());
        $this->assertSame($cause, $exception->getPrevious());
        $this->assertStringNotContainsString('sensitive', $exception->getMessage());
    }

    public function testSuccessfulNativeAPIRequest(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], '{"foo": "bar"}'),
        ]);
        $guzzleClient = new Client(['handler' => $mockHandler]);

        $actions = $this->getMockBuilder(DataverseActions::class)
            ->setConstructorArgs(array($this->configuration, $guzzleClient))
            ->getMockForAbstractClass();

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

        $actions = $this->getMockBuilder(DataverseActions::class)
            ->setConstructorArgs(array($this->configuration, $guzzleClient))
            ->getMockForAbstractClass();

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

        $actions = $this->getMockBuilder(DataverseActions::class)
            ->setConstructorArgs([$this->configuration, $guzzleClient])
            ->getMockForAbstractClass();

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

        $actions = $this->getMockBuilder(DataverseActions::class)
            ->setConstructorArgs(array($this->configuration, $guzzleClient))
            ->getMockForAbstractClass();

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

        $actions = $this->getMockBuilder(DataverseActions::class)
            ->setConstructorArgs(array($this->configuration, $guzzleClient))
            ->getMockForAbstractClass();

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

        $actions = $this->getMockBuilder(DataverseActions::class)
            ->setConstructorArgs([$this->configuration, $guzzleClient])
            ->getMockForAbstractClass();

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

        $actions = $this->getMockBuilder(DataverseActions::class)
            ->setConstructorArgs([$this->configuration, $guzzleClient])
            ->getMockForAbstractClass();

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

        $actions = $this->getMockBuilder(DataverseActions::class)
            ->setConstructorArgs([$this->configuration, $guzzleClient])
            ->getMockForAbstractClass();

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

        $actions = $this->getMockBuilder(DataverseActions::class)
            ->setConstructorArgs([$this->configuration, $guzzleClient])
            ->getMockForAbstractClass();

        try {
            $actions->nativeAPIRequest('GET', 'test');
            $this->fail('A DataverseException was expected');
        } catch (DataverseException $exception) {
            $this->assertSame(503, $exception->getCode());
            $this->assertSame('plugins.generic.dataverse.error.unavailable', $exception->getUserMessageKey());
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

        $actions = $this->getMockBuilder(DataverseActions::class)
            ->setConstructorArgs([$this->configuration, $guzzleClient])
            ->getMockForAbstractClass();

        try {
            $actions->nativeAPIRequest('GET', 'test');
            $this->fail('A DataverseException was expected');
        } catch (DataverseException $exception) {
            $this->assertSame(503, $exception->getCode());
            $this->assertSame(__('plugins.generic.dataverse.error.exception.unavailable'), $exception->getMessage());
        }
    }
}
