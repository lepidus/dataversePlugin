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
        $actions = new DatasetFileActions($configuration, new Client(['handler' => $stack]));

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

    public function testTokenIsNotSentOverAnotherScheme(): void
    {
        [$requests] = $this->downloadWith([
            new Response(302, ['Location' => 'http://test.dataverse.org/api/access/datafile/41']),
            new Response(200, [], 'content'),
        ]);

        $this->assertFalse($requests[1]['request']->hasHeader('X-Dataverse-key'));
    }

    public function testEndlessRedirectsAreNotFollowed(): void
    {
        $this->expectException(DataverseException::class);
        $this->downloadWith(array_fill(0, 10, new Response(302, ['Location' => '/again'])));
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
