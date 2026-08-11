<?php

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
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

        $actions = new ConcreteDataverseActions($this->configuration, $guzzleClient);

        try {
            $actions->nativeAPIRequest('GET', 'test');
            $this->fail('A DataverseException was expected');
        } catch (DataverseException $exception) {
            $this->assertSame(503, $exception->getCode());
            $this->assertSame(__('plugins.generic.dataverse.error.exception.unavailable'), $exception->getMessage());
        }
    }
}

class ConcreteDataverseActions extends DataverseActions
{
}
