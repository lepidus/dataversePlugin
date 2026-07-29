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
    private $controlledDataverseProcess;
    private $controlledDataverseUrl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configuration = new DataverseConfiguration();
        $this->configuration->setDataverseUrl('https://test.dataverse.org/dataverses/testDataverse');
        $this->configuration->setAPIToken('apiToken');
    }

    protected function tearDown(): void
    {
        if (is_resource($this->controlledDataverseProcess)) {
            proc_terminate($this->controlledDataverseProcess);
            proc_close($this->controlledDataverseProcess);
        }

        parent::tearDown();
    }

    public function testNativeApiUriCreation(): void
    {
        $actions = $this->getMockBuilder(DataverseActions::class)
            ->setConstructorArgs([$this->configuration])
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

    public function testNativeApiRequestAgainstControlledDataverse(): void
    {
        $this->startControlledDataverse();
        $this->configuration->setDataverseUrl($this->controlledDataverseUrl . '/dataverse/testDataverse');
        $this->configuration->setAPIToken('valid-token');
        $actions = $this->getMockBuilder(DataverseActions::class)
            ->setConstructorArgs([$this->configuration, new Client()])
            ->getMockForAbstractClass();

        $response = $actions->nativeAPIRequest('GET', $actions->getCurrentDataverseURI());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            'Dataverse de Exemplo Lepidus',
            json_decode($response->getBody(), true)['data']['name']
        );
    }

    public function testControlledDataverseExposesDefaultLicense(): void
    {
        $this->startControlledDataverse();
        $this->configuration->setDataverseUrl($this->controlledDataverseUrl . '/dataverse/testDataverse');
        $this->configuration->setAPIToken('valid-token');
        $actions = $this->getMockBuilder(DataverseActions::class)
            ->setConstructorArgs([$this->configuration, new Client()])
            ->getMockForAbstractClass();

        $response = $actions->nativeAPIRequest(
            'GET',
            $actions->createNativeAPIURI(['licenses'])
        );
        $licenses = json_decode($response->getBody(), true)['data'];
        $defaultLicenses = array_values(array_filter($licenses, function (array $license): bool {
            return $license['isDefault'];
        }));

        $this->assertCount(1, $defaultLicenses);
        $this->assertSame('CC0 1.0', $defaultLicenses[0]['name']);
    }

    /**
     * @dataProvider controlledDataverseErrorProvider
     */
    public function testControlledDataverseErrorIsPreserved(string $token, int $code, string $message): void
    {
        $this->startControlledDataverse();
        $this->configuration->setDataverseUrl($this->controlledDataverseUrl . '/dataverse/testDataverse');
        $this->configuration->setAPIToken($token);
        $actions = $this->getMockBuilder(DataverseActions::class)
            ->setConstructorArgs([$this->configuration, new Client()])
            ->getMockForAbstractClass();

        $this->expectException(DataverseException::class);
        $this->expectExceptionCode($code);
        $this->expectExceptionMessage($message);
        $actions->nativeAPIRequest('GET', $actions->getCurrentDataverseURI());
    }

    public function controlledDataverseErrorProvider(): array
    {
        return [
            'expired token' => ['expired-token', 401, 'API token has expired'],
            'temporarily unavailable' => ['unavailable-token', 503, 'Dataverse temporarily unavailable'],
        ];
    }

    private function startControlledDataverse(): void
    {
        if (is_resource($this->controlledDataverseProcess)) {
            return;
        }

        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        $this->assertNotFalse($socket, $errorMessage);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $port = substr($address, strrpos($address, ':') + 1);
        $router = __DIR__ . '/../../fixtures/controlledDataverse/router.php';
        $log = sys_get_temp_dir() . '/controlled-dataverse-' . getmypid() . '.log';
        $this->controlledDataverseProcess = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, $router],
            [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']],
            $pipes
        );
        $this->assertIsResource($this->controlledDataverseProcess);
        $this->controlledDataverseUrl = 'http://127.0.0.1:' . $port;

        $ready = false;
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $health = @file_get_contents($this->controlledDataverseUrl . '/health');
            if ($health !== false) {
                $ready = true;
                break;
            }
            usleep(20000);
        }
        $this->assertTrue($ready, 'Controlled Dataverse did not become ready');
    }
}
