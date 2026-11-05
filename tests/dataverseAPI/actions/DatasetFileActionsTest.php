<?php

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use PKP\config\Config;
use PKP\plugins\Hook;
use PKP\tests\PKPTestCase;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DatasetFileActions;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;

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
        string $actionsClass = DatasetFileActions::class
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

class ShortIngestWaitDatasetFileActions extends DatasetFileActions
{
    protected const INGEST_WAIT_TIMEOUT_SECONDS = 1;
}
