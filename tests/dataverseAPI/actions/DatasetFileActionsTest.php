<?php

use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DatasetFileActions;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PKP\config\Config;
use PKP\plugins\Hook;
use PKP\tests\PKPTestCase;

class DatasetFileActionsTest extends PKPTestCase
{
    private ?string $downloadedFilePath = null;
    private ?string $downloadedFileName = null;

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
            $this->assertFileExists($params[0]);
            $params[3] = true;
            return Hook::ABORT;
        });
    }

    private function createActions(): DatasetFileActions
    {
        $configuration = new DataverseConfiguration();
        $configuration->setDataverseUrl('https://test.dataverse.org/dataverses/testDataverse');
        $configuration->setAPIToken('apiToken');

        $handler = new MockHandler([new Response(200, [], 'research data contents')]);
        $client = new Client(['handler' => HandlerStack::create($handler)]);

        return new DatasetFileActions($configuration, $client);
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
}
