<?php

use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DatasetFileActions;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PKP\config\Config;
use PKP\plugins\Hook;
use PKP\tests\PKPTestCase;

class DatasetFileActionsTest extends PKPTestCase
{
    public function testUploadWaitsUntilIngestLockIsReleased(): void
    {
        $history = [];
        $handler = HandlerStack::create(new MockHandler([
            new Response(200, [], '{"data":[{"lockType":"Ingest"}]}'),
            new Response(200, [], '{"data":[]}'),
            new Response(200, [], '{"data":{"files":[{"dataFile":{"id":10}}]}}'),
        ]));
        $handler->push(Middleware::history($history));
        $actions = $this->createActions(new Client(['handler' => $handler]));

        $receipt = $actions->add('doi:10.1234/ABC', 'data.csv', __FILE__);

        $this->assertSame(10, $receipt[0]['id']);
        $this->assertSame(['GET', 'GET', 'POST'], array_map(
            fn ($entry) => $entry['request']->getMethod(),
            $history
        ));
        parse_str($history[0]['request']->getUri()->getQuery(), $query);
        $this->assertSame(['persistentId' => 'doi:10.1234/ABC', 'type' => 'Ingest'], $query);
        $this->assertSame('/api/datasets/:persistentId/locks', $history[0]['request']->getUri()->getPath());
    }

    public function testIngestWaitTimeoutDoesNotUpload(): void
    {
        $history = [];
        $handler = HandlerStack::create(new MockHandler(array_fill(
            0,
            10,
            new Response(200, [], '{"data":[{"lockType":"Ingest"}]}')
        )));
        $handler->push(Middleware::history($history));
        $actions = $this->createActions(new Client(['handler' => $handler]), ShortIngestWaitDatasetFileActions::class);

        try {
            $actions->add('doi:10.1234/ABC', 'data.csv', __FILE__);
            $this->fail('An active ingest must prevent uploading after the wait expires.');
        } catch (DataverseException $exception) {
            $this->assertInstanceOf(\APP\plugins\generic\dataverse\classes\exception\UploadNotSentException::class, $exception);
            $this->assertSame('data.csv', $exception->getFileName());
            $this->assertSame(__('plugins.generic.dataverse.error.ingestTimeout'), $exception->getMessage());
            $this->assertNotEmpty($history);
            $this->assertSame(['GET'], array_values(array_unique(array_map(
                fn ($entry) => $entry['request']->getMethod(),
                $history
            ))));
        }
    }

    public function testLockLookupFailureDoesNotUpload(): void
    {
        $history = [];
        $handler = HandlerStack::create(new MockHandler([
            new Response(401, [], '{"message":"Invalid API token"}'),
        ]));
        $handler->push(Middleware::history($history));
        $actions = $this->createActions(new Client(['handler' => $handler]));

        try {
            $actions->add('doi:10.1234/ABC', 'data.csv', __FILE__);
            $this->fail('A failed lock lookup must prevent uploading.');
        } catch (DataverseException $exception) {
            $this->assertSame(401, $exception->getCode());
            $this->assertCount(1, $history);
            $this->assertSame('GET', $history[0]['request']->getMethod());
        }
    }

    /** @dataProvider malformedLockResponses */
    public function testMalformedLockResponseDoesNotUpload(string $response): void
    {
        $actions = $this->createActions(new Client([
            'handler' => new MockHandler([new Response(200, [], $response)]),
        ]));

        $this->expectException(DataverseException::class);
        $actions->add('doi:10.1234/ABC', 'data.csv', __FILE__);
    }

    public static function malformedLockResponses(): array
    {
        return [
            'missing locks' => ['{"status":"OK"}'],
            'object instead of lock list' => ['{"data":{}}'],
            'invalid JSON' => ['not JSON'],
        ];
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
        $this->expectException(\APP\plugins\generic\dataverse\classes\exception\DataverseException::class);
        $actions->add('doi:10.1234/ABC', 'source.zip', __FILE__);
    }

    public function testIngestWaitDoesNotSendARequestWithLessThanOneMillisecondRemaining(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], '{"data":[]}'),
            new Response(200, [], '{"data":{"files":[{"dataFile":{"id":10}}]}}'),
        ]));
        $stack->push(Middleware::history($history));
        $actions = $this->createActions(new Client(['handler' => $stack]), SubMillisecondIngestWaitDatasetFileActions::class);
        try {
            $actions->add('doi:10.1234/ABC', 'data.csv', __FILE__);
            $this->fail('A sub-millisecond request timeout must never be sent to cURL');
        } catch (DataverseException $e) {
            $this->assertSame([], $history);
        }
    }

    public function testLostUploadResponseIsNotReportedAsAnUnsentUpload(): void
    {
        $actions = $this->createActions(new Client([
            'handler' => new MockHandler([
                new Response(200, [], '{"data":[]}'),
                new \GuzzleHttp\Exception\ConnectException(
                    'Upload response timed out',
                    new \GuzzleHttp\Psr7\Request('POST', 'https://test.dataverse.org/api/datasets/:persistentId/add'),
                    null,
                    ['errno' => 28]
                ),
            ]),
        ]));
        try {
            $actions->add('doi:10.1234/ABC', 'data.csv', __FILE__);
            $this->fail('The upload must time out');
        } catch (DataverseException $exception) {
            $this->assertNotInstanceOf(
                \APP\plugins\generic\dataverse\classes\exception\UploadNotSentException::class,
                $exception
            );
            $this->assertSame('timeout', $exception->getFailureCategory());
        }
    }

    private function actions(array $response): DatasetFileActions
    {
        return $this->createActions(new Client([
            'handler' => new MockHandler([
                new Response(200, [], '{"data":[]}'),
                new Response(200, [], json_encode($response)),
            ]),
        ]));
    }

    private function createActions(
        Client $client,
        string $actionsClass = ResolvedStorageDatasetFileActions::class
    ): DatasetFileActions {
        $configuration = new DataverseConfiguration();
        $configuration->setDataverseUrl('https://test.dataverse.org/dataverse/test');
        $configuration->setAPIToken('test-token');
        return new $actionsClass($configuration, $client);
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
        $actions = $this->createActions(new Client(['handler' => $stack]));

        $downloadArguments = null;
        Hook::add('FileManager::downloadFile', function ($hookName, $args) use (&$downloadArguments) {
            $downloadArguments = $args;
            return Hook::ABORT;
        });

        try {
            $actions->download(41, $filename);
            $this->assertSame(['.', '..'], scandir($filesDir));
        } finally {
            Hook::clear('FileManager::downloadFile');
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

        $this->assertTrue($requests[0]['request']->hasHeader('X-Dataverse-key'));
        $this->assertSame('storage.example.org', $requests[1]['request']->getUri()->getHost());
        $this->assertFalse($requests[1]['request']->hasHeader('X-Dataverse-key'));
    }

    public function testTokenIsKeptOnDownloadRedirectsWithinTheDataverseServer(): void
    {
        [$requests] = $this->downloadWith([
            new Response(302, ['Location' => '/api/access/datafile/41/redirected']),
            new Response(200, [], 'content'),
        ]);

        $this->assertTrue($requests[1]['request']->hasHeader('X-Dataverse-key'));
    }

    public function testEndlessDownloadRedirectsAreNotFollowed(): void
    {
        $this->expectException(DataverseException::class);
        $this->downloadWith(array_fill(0, 10, new Response(302, ['Location' => '/again'])));
    }

    /**
     * @dataProvider redirectsWithoutDestination
     */
    public function testRedirectWithoutDestinationIsNotServedAsTheFile(int $status): void
    {
        $this->expectException(DataverseException::class);
        $this->downloadWith([new Response($status, [], '<html>redirect</html>')]);
    }

    public static function redirectsWithoutDestination(): array
    {
        return [[302], [303], [304]];
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

    public function testDownloadRedirectedToStoragePinsEveryResolvedAddress(): void
    {
        [$requests] = $this->downloadWith([
            new Response(303, ['Location' => 'https://dual-stack.example.org:8443/file']),
            new Response(200, [], 'content'),
        ]);

        $this->assertSame(
            ['dual-stack.example.org:8443:' . ResolvedStorageDatasetFileActions::PUBLIC_ADDRESS . ',[2600:1f18:4a3:6900::1]'],
            $requests[1]['options']['curl'][CURLOPT_RESOLVE]
        );
    }

    private function downloadThroughProxy(string $location): array
    {
        $previousProxy = getenv('https_proxy');
        putenv('https_proxy=http://proxy.example.org:3128');

        try {
            return $this->downloadWith([
                new Response(303, ['Location' => $location]),
                new Response(200, [], 'content'),
            ]);
        } finally {
            putenv($previousProxy === false ? 'https_proxy' : 'https_proxy=' . $previousProxy);
        }
    }

    public function testDownloadThroughAProxyFollowsStorageTheProxyResolves(): void
    {
        [$requests] = $this->downloadThroughProxy('https://unknown.example.org/file');

        $this->assertSame('https://unknown.example.org/file', (string) $requests[1]['request']->getUri());
        $this->assertArrayNotHasKey(CURLOPT_RESOLVE, $requests[1]['options']['curl']);
    }

    public function testDownloadThroughAProxyIsNotRedirectedToAHostResolvingToAPrivateAddress(): void
    {
        $previousErrorLog = ini_set('error_log', '/dev/null');
        $this->expectException(DataverseException::class);

        try {
            $this->downloadThroughProxy('https://internal.example.org/file');
        } finally {
            ini_set('error_log', $previousErrorLog);
        }
    }

    /**
     * @dataProvider publicAddressLiterals
     */
    public function testDownloadRedirectedToAPublicAddressLiteralIsNotPinned(string $location): void
    {
        [$requests] = $this->downloadWith([
            new Response(303, ['Location' => $location]),
            new Response(200, [], 'content'),
        ]);

        $this->assertSame($location, (string) $requests[1]['request']->getUri());
        $this->assertArrayNotHasKey(CURLOPT_RESOLVE, $requests[1]['options']['curl']);
    }

    public static function publicAddressLiterals(): array
    {
        return [
            'IPv4 address' => ['https://93.184.215.14/file'],
            'IPv6 address' => ['https://[2600:1f18:4a3:6900::1]/file'],
            'NAT64 public address' => ['https://[64:ff9b::5db8:d70e]/file'],
        ];
    }

    /**
     * @dataProvider storageRedirectsOutsideThePublicNetwork
     */
    public function testDownloadIsNotRedirectedOutsideThePublicNetwork(string $location): void
    {
        $previousErrorLog = ini_set('error_log', '/dev/null');

        try {
            $this->downloadWith([
                new Response(303, ['Location' => $location]),
                new Response(200, [], 'internal service response'),
            ]);
            $this->fail('The download followed the redirect to ' . $location);
        } catch (DataverseException $e) {
            $this->assertSame(503, $e->getCode());
        } finally {
            ini_set('error_log', $previousErrorLog);
        }
    }

    public static function storageRedirectsOutsideThePublicNetwork(): array
    {
        return [
            'loopback address' => ['https://127.0.0.1:8080/private'],
            'cloud metadata address' => ['https://169.254.169.254/latest/meta-data/'],
            'private network address' => ['https://10.0.0.5/file'],
            'IPv4-mapped loopback address' => ['https://[::ffff:127.0.0.1]/file'],
            'shared address space' => ['https://100.100.100.200/latest/meta-data/'],
            'IETF protocol assignments' => ['https://192.0.0.8/file'],
            'benchmarking address' => ['https://198.18.0.1/file'],
            'NAT64 private address' => ['https://[64:ff9b::a00:5]/file'],
            'NAT64 metadata address' => ['https://[64:ff9b::a9fe:a9fe]/file'],
            'local-use NAT64 address' => ['https://[64:ff9b:1::5db8:d70e]/file'],
            '6to4 address' => ['https://[2002:5db8:d70e::1]/file'],
            'site-local address' => ['https://[fec0::1]/file'],
            'multicast address' => ['https://[ff02::1]/file'],
            'host resolving to a private address' => ['https://internal.example.org/file'],
            'host that does not resolve' => ['https://unknown.example.org/file'],
            'storage without HTTPS' => ['http://storage.example.org/file'],
        ];
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

    /**
     * @dataProvider fileNamesWithHeaderCharacters
     */
    public function testDownloadNameCannotBreakTheContentDispositionHeader(string $name, string $expected): void
    {
        [, $downloadArguments] = $this->downloadWith([new Response(200, [], 'content')], $name);

        $this->assertSame($expected, $downloadArguments[4]);
    }

    public static function fileNamesWithHeaderCharacters(): array
    {
        return [
            'line break' => ["data\r\n.html", 'data.html'],
            'double quote' => ['da"ta.csv', 'data.csv'],
            'backslash' => ['da\\ta.csv', 'data.csv'],
        ];
    }

    public function testDownloadKeepsTheMediaTypeGivenByTheFileExtension(): void
    {
        [, $downloadArguments] = $this->downloadWith([new Response(200, [], "year,value\n2026,1\n")], 'data.csv');

        $this->assertSame('text/csv', $downloadArguments[1]);
    }

    public function testFailedDownloadRemovesItsTemporaryDirectory(): void
    {
        $filesDir = realpath(Config::getVar('files', 'files_dir'));
        $directoriesBefore = glob($filesDir . '/datasetFile*');
        $configuration = new DataverseConfiguration();
        $configuration->setDataverseUrl('https://test.dataverse.org/dataverses/testDataverse');
        $configuration->setAPIToken('apiToken');
        $actions = new DatasetFileActions($configuration, new Client([
            'handler' => HandlerStack::create(new MockHandler([new Response(500, [], 'Internal error')])),
        ]));

        try {
            $actions->download(1, 'Data.csv');
            $this->fail('The Dataverse error was not reported');
        } catch (DataverseException $e) {
            $this->assertSame($directoriesBefore, glob($filesDir . '/datasetFile*'));
        }
    }
}

class ResolvedStorageDatasetFileActions extends DatasetFileActions
{
    public const PUBLIC_ADDRESS = '93.184.215.14';

    protected function resolveHostAddresses(string $host): array
    {
        return [
            'storage.example.org' => [self::PUBLIC_ADDRESS],
            'test.dataverse.org' => [self::PUBLIC_ADDRESS],
            'internal.example.org' => [self::PUBLIC_ADDRESS, '10.0.0.5'],
            'dual-stack.example.org' => [self::PUBLIC_ADDRESS, '2600:1f18:4a3:6900::1'],
        ][$host] ?? [];
    }
}

class ShortIngestWaitDatasetFileActions extends ResolvedStorageDatasetFileActions
{
    protected const INGEST_WAIT_TIMEOUT_SECONDS = 1;
}

class SubMillisecondIngestWaitDatasetFileActions extends DatasetFileActions
{
    protected const INGEST_WAIT_TIMEOUT_SECONDS = 0.0009;
}
