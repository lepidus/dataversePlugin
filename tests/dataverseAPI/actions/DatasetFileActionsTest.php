<?php

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use PKP\config\Config;
use PKP\tests\PKPTestCase;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DatasetFileActions;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;

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
        $this->expectException(\APP\plugins\generic\dataverse\classes\exception\DataverseException::class);
        $actions->add('doi:10.1234/ABC', 'source.zip', __FILE__);
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
