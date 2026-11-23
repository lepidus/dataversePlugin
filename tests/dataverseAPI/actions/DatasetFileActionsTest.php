<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.dataverseAPI.actions.DatasetFileActions');
import('plugins.generic.dataverse.classes.dataverseConfiguration.DataverseConfiguration');
import('plugins.generic.dataverse.classes.exception.DataverseException');

class DatasetFileActionsTest extends PKPTestCase
{
    private $errorLog;
    private $errorLogFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->errorLog = ini_get('error_log');
        $this->errorLogFile = tempnam(sys_get_temp_dir(), 'datasetFileActionsLog');
        ini_set('error_log', $this->errorLogFile);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->errorLog);
        unlink($this->errorLogFile);
        parent::tearDown();
    }

    public function testArchiveUploadReturnsEveryRemoteFileReceipt(): void
    {
        $actions = $this->actions(['data' => ['files' => [
            ['dataFile' => ['id' => 10, 'checksum' => ['type' => 'MD5', 'value' => 'abc'], 'filesize' => 12]],
            ['dataFile' => ['id' => 11, 'checksum' => ['type' => 'MD5', 'value' => 'def'], 'filesize' => 20]],
        ]]]);
        $receipt = $actions->add('doi:10.1234/ABC', 'source.zip', __FILE__);
        $this->assertSame([
            ['id' => 10, 'checksum' => ['type' => 'MD5', 'value' => 'abc'], 'size' => 12],
            ['id' => 11, 'checksum' => ['type' => 'MD5', 'value' => 'def'], 'size' => 20],
        ], $receipt);
    }

    public function testSuccessWithoutFileReceiptIsNotConfirmed(): void
    {
        $actions = $this->actions(['data' => ['files' => []]]);
        $this->expectException(DataverseException::class);
        $actions->add('doi:10.1234/ABC', 'source.zip', __FILE__);
    }

    public function testFailedDownloadRemovesTemporaryDirectory(): void
    {
        $filesDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('datasetFileTest');
        mkdir($filesDir);
        $config = &Config::getData();
        $originalFilesDir = $config['files']['files_dir'];
        $config['files']['files_dir'] = $filesDir;

        $configuration = new DataverseConfiguration();
        $configuration->setDataverseUrl('https://test.dataverse.org/dataverse/test');
        $configuration->setAPIToken('test-token');
        $client = $this->createMock(Client::class);
        $client->method('request')->willThrowException(
            new ConnectException('Transfer failed', new Request('GET', 'https://test.dataverse.org'))
        );
        $actions = new DatasetFileActions($configuration, $client);

        try {
            $actions->download(41, 'data.csv');
            $this->fail('A failed transfer must raise a DataverseException.');
        } catch (DataverseException $e) {
            $this->assertSame(['.', '..'], scandir($filesDir));
        } finally {
            $config['files']['files_dir'] = $originalFilesDir;
            rmdir($filesDir);
        }
    }

    public function testDownloadDoesNotReadTheBodyOfTheTransferredFile(): void
    {
        $filesDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('datasetFileTest');
        mkdir($filesDir);
        $config = &Config::getData();
        $originalFilesDir = $config['files']['files_dir'];
        $config['files']['files_dir'] = $filesDir;

        $resource = fopen('php://memory', 'r+');
        $closedBody = Utils::streamFor($resource);
        fclose($resource);

        $client = $this->createMock(Client::class);
        $client->method('request')->willReturn(new Response(200, [], $closedBody));

        $configuration = new DataverseConfiguration();
        $configuration->setDataverseUrl('https://test.dataverse.org/dataverse/test');
        $configuration->setAPIToken('test-token');
        $actions = new DatasetFileActions($configuration, $client);

        HookRegistry::register('FileManager::downloadFile', function () {
            return true;
        });

        try {
            $actions->download(41, 'data.csv');
            $this->assertSame(['.', '..'], scandir($filesDir));
        } finally {
            HookRegistry::clear('FileManager::downloadFile');
            $config['files']['files_dir'] = $originalFilesDir;
            array_map('rmdir', glob($filesDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR));
            rmdir($filesDir);
        }
    }

    private function downloadWith(array $responses, string $filename = 'data.csv'): array
    {
        $filesDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('datasetFileTest');
        mkdir($filesDir);
        $config = &Config::getData();
        $originalFilesDir = $config['files']['files_dir'];
        $config['files']['files_dir'] = $filesDir;

        $requests = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($requests));

        $configuration = new DataverseConfiguration();
        $configuration->setDataverseUrl('https://test.dataverse.org/dataverse/test');
        $configuration->setAPIToken('test-token');
        $actions = new ResolvedStorageDatasetFileActions($configuration, new Client(['handler' => $stack]));

        $downloadArguments = null;
        HookRegistry::register('FileManager::downloadFile', function ($hookName, $args) use (&$downloadArguments) {
            $downloadArguments = $args;
            return true;
        });

        try {
            $actions->download(41, $filename);
            $this->assertSame(['.', '..'], scandir($filesDir));
        } finally {
            HookRegistry::clear('FileManager::downloadFile');
            $config['files']['files_dir'] = $originalFilesDir;
            exec('rm -rf ' . escapeshellarg($filesDir));
        }

        return [$requests, $downloadArguments];
    }

    public function testTokenIsNotSentToExternalStorage(): void
    {
        [$requests] = $this->downloadWith([
            new Response(303, ['Location' => 'https://storage.example.org/bucket/file?signature=abc']),
            new Response(200, [], 'content'),
        ]);

        $this->assertSame('test.dataverse.org', $requests[0]['request']->getUri()->getHost());
        $this->assertTrue($requests[0]['request']->hasHeader('X-Dataverse-key'));
        $this->assertSame('storage.example.org', $requests[1]['request']->getUri()->getHost());
        $this->assertFalse($requests[1]['request']->hasHeader('X-Dataverse-key'));
    }

    public function testTokenIsKeptOnRedirectsWithinTheDataverseServer(): void
    {
        [$requests] = $this->downloadWith([
            new Response(302, ['Location' => '/api/access/datafile/41/redirected']),
            new Response(200, [], 'content'),
        ]);

        $this->assertSame('/api/access/datafile/41/redirected', $requests[1]['request']->getUri()->getPath());
        $this->assertTrue($requests[1]['request']->hasHeader('X-Dataverse-key'));
    }

    public function testDownloadIsNotRedirectedOverAnotherScheme(): void
    {
        $this->expectException(DataverseException::class);
        $this->downloadWith([
            new Response(302, ['Location' => 'http://test.dataverse.org/api/access/datafile/41']),
            new Response(200, [], 'content'),
        ]);
    }

    public function testDownloadRedirectedToStoragePinsTheResolvedPublicAddress(): void
    {
        [$requests] = $this->downloadWith([
            new Response(303, ['Location' => 'https://storage.example.org/file']),
            new Response(200, [], 'content'),
        ]);

        $this->assertSame(
            ['storage.example.org:443:' . ResolvedStorageDatasetFileActions::PUBLIC_ADDRESS],
            $requests[1]['options']['curl'][CURLOPT_RESOLVE]
        );
    }

    /**
     * @dataProvider storageRedirectsOutsideThePublicNetwork
     */
    public function testDownloadIsNotRedirectedOutsideThePublicNetwork(string $location): void
    {
        try {
            $this->downloadWith([
                new Response(303, ['Location' => $location]),
                new Response(200, [], 'internal service response'),
            ]);
            $this->fail('The download followed the redirect to ' . $location);
        } catch (DataverseException $e) {
            $this->assertSame(503, $e->getCode());
        }
    }

    public function storageRedirectsOutsideThePublicNetwork(): array
    {
        return [
            'loopback address' => ['https://127.0.0.1:8080/private'],
            'cloud metadata address' => ['https://169.254.169.254/latest/meta-data/'],
            'private network address' => ['https://10.0.0.5/file'],
            'IPv4-mapped loopback address' => ['https://[::ffff:127.0.0.1]/file'],
            'host resolving to a private address' => ['https://internal.example.org/file'],
            'host that does not resolve' => ['https://unknown.example.org/file'],
            'storage without HTTPS' => ['http://storage.example.org/file'],
            'shared address space' => ['https://100.64.0.5/file'],
            'benchmarking network address' => ['https://198.18.0.5/file'],
            'NAT64 address of a private network' => ['https://[64:ff9b::a00:5]/file'],
            'local-use NAT64 address' => ['https://[64:ff9b:1::a00:5]/file'],
            '6to4 address of a private network' => ['https://[2002:a00:5::1]/file'],
            'IPv4-compatible loopback address' => ['https://[::7f00:1]/file'],
        ];
    }

    public function testEndlessRedirectsAreNotFollowed(): void
    {
        $this->expectException(DataverseException::class);
        $this->downloadWith(array_fill(0, 10, new Response(302, ['Location' => '/again'])));
    }

    public function testDownloadRedirectedToANat64AddressOfAPublicNetworkIsFollowed(): void
    {
        [$requests] = $this->downloadWith([
            new Response(303, ['Location' => 'https://[64:ff9b::5db8:d70e]/file']),
            new Response(200, [], 'content'),
        ]);

        $this->assertCount(2, $requests);
    }

    public function redirectsWithoutDestination(): array
    {
        return [[302], [303], [304]];
    }

    /**
     * @dataProvider redirectsWithoutDestination
     */
    public function testRedirectWithoutDestinationIsNotServedAsTheFile(int $status): void
    {
        $this->expectException(DataverseException::class);
        $this->downloadWith([new Response($status, [], '<html>redirect</html>')]);
    }

    public function testTokenIsNotReattachedAfterLeavingTheServer(): void
    {
        [$requests] = $this->downloadWith([
            new Response(303, ['Location' => 'https://storage.example.org/file']),
            new Response(302, ['Location' => 'https://test.dataverse.org/api/admin/settings']),
            new Response(200, [], 'content'),
        ]);

        $this->assertSame('test.dataverse.org', $requests[2]['request']->getUri()->getHost());
        $this->assertFalse($requests[2]['request']->hasHeader('X-Dataverse-key'));
    }

    public function testMalformedRedirectIsReportedAsUnavailable(): void
    {
        $this->expectException(DataverseException::class);
        $this->expectExceptionCode(503);
        $this->downloadWith([new Response(302, ['Location' => 'http://:'])]);
    }

    public function testExpiredStorageLinkIsNotReportedAsInvalidToken(): void
    {
        $this->expectExceptionCode(503);
        $this->downloadWith([
            new Response(303, ['Location' => 'https://storage.example.org/file']),
            new Response(401, [], '{"message":"expired"}'),
        ]);
    }

    public function fileNamesWithHeaderCharacters(): array
    {
        return [
            'line break' => ["data\r\n.html", 'data.html'],
            'double quote' => ['da"ta.csv', 'data.csv'],
            'backslash' => ['da\\ta.csv', 'data.csv'],
        ];
    }

    /**
     * @dataProvider fileNamesWithHeaderCharacters
     */
    public function testDownloadNameCannotBreakTheContentDispositionHeader(string $name, string $expected): void
    {
        [, $downloadArguments] = $this->downloadWith([new Response(200, [], 'content')], $name);

        $this->assertSame($expected, $downloadArguments[4]);
    }

    public function testDownloadKeepsTheMediaTypeGivenByTheFileExtension(): void
    {
        [, $downloadArguments] = $this->downloadWith([new Response(200, [], "year,value\n2026,1\n")], 'data.csv');

        $this->assertSame('text/csv', $downloadArguments[1]);
    }

    public function testDownloadUsesItsOwnTimeoutOnEveryTransfer(): void
    {
        [$requests] = $this->downloadWith([
            new Response(303, ['Location' => 'https://storage.example.org/file']),
            new Response(200, [], 'content'),
        ]);

        $this->assertSame(300, $requests[0]['options']['timeout']);
        $this->assertSame(300, $requests[1]['options']['timeout']);
    }

    public function testFileNameFromDataverseIsNotUsedOnDisk(): void
    {
        $longName = str_repeat('a', 300) . '.csv';

        [, $downloadArguments] = $this->downloadWith([new Response(200, [], 'content')], $longName);

        $this->assertSame('datasetFile', basename($downloadArguments[0]));
        $this->assertSame($longName, $downloadArguments[4]);
    }

    private function actions(array $response): DatasetFileActions
    {
        $configuration = new DataverseConfiguration();
        $configuration->setDataverseUrl('https://test.dataverse.org/dataverse/test');
        $configuration->setAPIToken('test-token');
        return new DatasetFileActions($configuration, new Client([
            'handler' => new MockHandler([new Response(200, [], json_encode($response))]),
        ]));
    }
}

class ResolvedStorageDatasetFileActions extends DatasetFileActions
{
    public const PUBLIC_ADDRESS = '93.184.215.14';

    protected function resolveHostAddresses(string $host): array
    {
        $addresses = [
            'storage.example.org' => [self::PUBLIC_ADDRESS],
            'test.dataverse.org' => [self::PUBLIC_ADDRESS],
            'internal.example.org' => [self::PUBLIC_ADDRESS, '10.0.0.5'],
        ];

        return $addresses[$host] ?? [];
    }
}
