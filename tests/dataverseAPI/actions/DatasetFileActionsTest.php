<?php

use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DatasetFileActions;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
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
        $configuration = new DataverseConfiguration();
        $configuration->setDataverseUrl('https://test.dataverse.org/dataverses/testDataverse');
        $configuration->setAPIToken('apiToken');

        $handler = new MockHandler([new Response($status, [], $contents)]);
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
        ini_set('error_log', '/dev/null');
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
