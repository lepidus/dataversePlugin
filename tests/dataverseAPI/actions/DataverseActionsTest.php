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
        $actions = $this->getMockBuilder(DataverseActions::class)
            ->setConstructorArgs([$this->configuration])
            ->getMockForAbstractClass();

        $uri = $actions->createNativeAPIURI('datasets', ':persistentId?persistentId=doi:10.12345/FK2/123456');

        $this->assertEquals(
            'https://test.dataverse.org/api/datasets/:persistentId?persistentId=doi:10.12345/FK2/123456',
            $uri
        );
    }

    public function testGetCurrentDataverseUri(): void
    {
        $actions = $this->getMockBuilder(DataverseActions::class)
            ->setConstructorArgs([$this->configuration])
            ->getMockForAbstractClass();

        $uri = $actions->getCurrentDataverseURI();

        $this->assertEquals(
            'https://test.dataverse.org/api/dataverses/testDataverse',
            $uri
        );
    }

    public function testGetRootDataverseUri(): void
    {
        $actions = $this->getMockBuilder(DataverseActions::class)
            ->setConstructorArgs([$this->configuration])
            ->getMockForAbstractClass();

        $uri = $actions->getRootDataverseURI();

        $this->assertEquals(
            'https://test.dataverse.org/api/dataverses/:root',
            $uri
        );
    }

    public function testSwordApiUriCreation(): void
    {
        $actions = $this->getMockBuilder(DataverseActions::class)
            ->setConstructorArgs([$this->configuration])
            ->getMockForAbstractClass();

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

        $actions = $this->getMockBuilder(DataverseActions::class)
            ->setConstructorArgs([$this->configuration, $guzzleClient])
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
            ->setConstructorArgs([$this->configuration, $guzzleClient])
            ->getMockForAbstractClass();

        $this->expectException(DataverseException::class);
        $this->expectExceptionMessage('Error Communicating with Server');
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
        $this->expectExceptionMessage('Failed to connect to Dataverse');
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
            ->setConstructorArgs([$this->configuration, $guzzleClient])
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
            ->setConstructorArgs([$this->configuration, $guzzleClient])
            ->getMockForAbstractClass();

        $this->expectException(DataverseException::class);
        $this->expectExceptionCode(500);
        $this->expectExceptionMessage('Error Communicating with Server');
        $actions->nativeAPIRequest('GET', 'test');
    }
}
