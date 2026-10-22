<?php

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use PKP\config\Config;
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
