<?php

use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DatasetFileActions;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\config\Config;
use PKP\core\PKPString;
use PKP\plugins\Hook;
use PKP\tests\PKPTestCase;

class DatasetFileActionsTest extends PKPTestCase
{
    private ?string $downloadedFilePath = null;
    private ?string $downloadedFileName = null;
    private ?string $downloadedMediaType = null;

    protected function getMockedRegistryKeys(): array
    {
        return [...parent::getMockedRegistryKeys(), 'hooks'];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Hook::add('FileManager::downloadFile', function ($hookName, $params) {
            $this->downloadedFilePath = realpath($params[0]);
            $this->downloadedFileName = $params[4];
            $this->downloadedMediaType = $params[1] ?? PKPString::mime_content_type($params[0]);
            $this->assertFileExists($params[0]);
            $params[3] = true;
            return Hook::ABORT;
        });
    }

    private function createActions(string $contents = 'research data contents', int $status = 200): DatasetFileActions
    {
        return $this->createActionsRespondingWith([new Response($status, [], $contents)]);
    }

    private function createActionsRespondingWith(
        array $responses,
        array &$history = [],
        string $actionsClass = DatasetFileActions::class
    ): DatasetFileActions {
        $configuration = new DataverseConfiguration();
        $configuration->setDataverseUrl('https://test.dataverse.org/dataverses/testDataverse');
        $configuration->setAPIToken('apiToken');

        $handler = HandlerStack::create(new MockHandler($responses));
        $handler->push(Middleware::history($history));

        return new $actionsClass($configuration, new Client(['handler' => $handler]));
    }

    private function requestMethods(array $history): array
    {
        return array_map(fn (array $transaction) => $transaction['request']->getMethod(), $history);
    }

    public function testUploadWaitsUntilTheIngestLockIsReleased(): void
    {
        $history = [];
        $actions = $this->createActionsRespondingWith([
            new Response(200, [], '{"data":[{"lockType":"Ingest"}]}'),
            new Response(200, [], '{"data":[]}'),
            new Response(200, [], '{"status":"OK"}'),
        ], $history);

        $actions->add('doi:10.1234/ABC', 'data.csv', __FILE__);

        $this->assertSame(['GET', 'GET', 'POST'], $this->requestMethods($history));
        $lockRequestUri = $history[0]['request']->getUri();
        parse_str($lockRequestUri->getQuery(), $query);
        $this->assertSame('/api/datasets/:persistentId/locks', $lockRequestUri->getPath());
        $this->assertSame(['persistentId' => 'doi:10.1234/ABC', 'type' => 'Ingest'], $query);
    }

    public function testUploadIsNotSentWhileTheIngestLockOutlastsTheWait(): void
    {
        $history = [];
        $actions = $this->createActionsRespondingWith(
            array_fill(0, 10, new Response(200, [], '{"data":[{"lockType":"Ingest"}]}')),
            $history,
            ShortIngestWaitDatasetFileActions::class
        );

        try {
            $actions->add('doi:10.1234/ABC', 'data.csv', __FILE__);
            $this->fail('The upload was sent while the dataset was locked');
        } catch (DataverseException $e) {
            $this->assertSame(__('plugins.generic.dataverse.error.ingestTimeout'), $e->getMessage());
            $this->assertNotEmpty($history);
            $this->assertSame(['GET'], array_unique($this->requestMethods($history)));
        }
    }

    public function testUploadIsNotSentWhenTheLockLookupFails(): void
    {
        $history = [];
        $actions = $this->createActionsRespondingWith([new Response(401, [], '{"message":"Invalid API token"}')], $history);

        try {
            $actions->add('doi:10.1234/ABC', 'data.csv', __FILE__);
            $this->fail('The upload was sent without checking the dataset locks');
        } catch (DataverseException $e) {
            $this->assertSame(401, $e->getCode());
            $this->assertSame(['GET'], $this->requestMethods($history));
        }
    }

    #[DataProvider('malformedLockResponses')]
    public function testUploadIsNotSentWhenTheLockResponseIsMalformed(string $lockResponse): void
    {
        $history = [];
        $actions = $this->createActionsRespondingWith([new Response(200, [], $lockResponse)], $history);

        try {
            $actions->add('doi:10.1234/ABC', 'data.csv', __FILE__);
            $this->fail('The upload was sent without a valid lock list');
        } catch (DataverseException $e) {
            $this->assertSame(['GET'], $this->requestMethods($history));
        }
    }

    public static function malformedLockResponses(): array
    {
        return [
            'missing locks' => ['{"status":"OK"}'],
            'object instead of lock list' => ['{"data":{"lockType":"Ingest"}}'],
            'invalid JSON' => ['not JSON'],
        ];
    }

    public function testDownloadKeepsTheFileInsideTheFilesDirectory(): void
    {
        $filesDir = realpath(Config::getVar('files', 'files_dir'));
        $escapedPath = dirname($filesDir) . DIRECTORY_SEPARATOR . 'dataverseDownloadEscape.txt';
        @unlink($escapedPath);

        $this->createActions()->download(2025434, '../../dataverseDownloadEscape.txt');

        $this->assertFileDoesNotExist($escapedPath);
        $this->assertStringStartsWith($filesDir . DIRECTORY_SEPARATOR, $this->downloadedFilePath);
        $this->assertSame('../../dataverseDownloadEscape.txt', $this->downloadedFileName);
    }

    public function testDownloadKeepsTheMediaTypeGivenByTheFileExtension(): void
    {
        $this->createActions("year,value\n2026,1\n")->download(2025434, 'Data.csv');

        $this->assertSame('text/csv', $this->downloadedMediaType);
    }

    public function testDownloadNameCannotBreakTheContentDispositionHeader(): void
    {
        $this->createActions()->download(2025434, "Data \"final\".csv");

        $this->assertSame('Data final.csv', $this->downloadedFileName);
    }

    public function testFailedDownloadRemovesItsTemporaryDirectory(): void
    {
        $filesDir = realpath(Config::getVar('files', 'files_dir'));
        $directoriesBefore = glob($filesDir . '/datasetFile*');

        try {
            $this->createActions('Internal error', 500)->download(2025434, 'Data.csv');
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
