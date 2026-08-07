<?php

use PKP\tests\PKPTestCase;
use APP\plugins\generic\dataverse\report\classes\DataverseStatsReport;
use APP\plugins\generic\dataverse\DataversePlugin;

class DataverseStatsReportTest extends PKPTestCase
{
    private DataverseStatsReport $report;
    private string $locale = 'en';
    private string $application = 'ojs2';
    private int $acceptedSubmissions = 40;
    private int $acceptedSubmissionsWithDataset = 35;
    private int $declinedSubmissions = 200;
    private int $declinedSubmissionsWithDataset = 50;
    private int $datasetsWithDepositError = 25;
    private int $datasetsWithPublishError = 5;
    private int $filesInDatasets = 125;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializePluginLocaleData();

        $this->report = $this->createTestReport($this->application, $this->locale);
    }

    private function initializePluginLocaleData(): void
    {
        $plugin = new DataversePlugin();
        $plugin->pluginPath = 'plugins/generic/dataverse';
        $plugin->addLocaleData();
    }

    private function createTestReport(string $application, string $locale): DataverseStatsReport
    {
        $report = new DataverseStatsReport($application, $locale);

        if ($application == 'ojs2') {
            $report->setAcceptedCount($this->acceptedSubmissions);
            $report->setAcceptedWithDatasetCount($this->acceptedSubmissionsWithDataset);
        }

        $report->setDeclinedCount($this->declinedSubmissions);
        $report->setDeclinedWithDatasetCount($this->declinedSubmissionsWithDataset);
        $report->setWithDepositErrorCount($this->datasetsWithDepositError);
        $report->setWithPublishErrorCount($this->datasetsWithPublishError);
        $report->setDatasetFilesCount($this->filesInDatasets);

        return $report;
    }

    private function getExpectedHeaders(string $application): array
    {
        $headers = [];

        if ($application == 'ojs2') {
            $headers = [
                __('plugins.generic.dataverse.report.headers.acceptedSubmissions'),
                __('plugins.generic.dataverse.report.headers.acceptedSubmissionsWithDataset'),
            ];
        } elseif ($application == 'ops') {
            $headers = [
                __('plugins.generic.dataverse.report.headers.publishedSubmissions'),
                __('plugins.generic.dataverse.report.headers.publishedSubmissionsWithDataset'),
            ];
        }

        return array_merge(
            $headers,
            [
                __('plugins.generic.dataverse.report.headers.declinedSubmissions'),
                __('plugins.generic.dataverse.report.headers.declinedSubmissionsWithDataset'),
                __('plugins.generic.dataverse.report.headers.datasetsWithDepositError'),
                __('plugins.generic.dataverse.report.headers.datasetsWithPublishError'),
                __('plugins.generic.dataverse.report.headers.filesInDatasets')
            ]
        );
    }

    public function testReportHasStatsData(): void
    {
        $this->assertEquals($this->acceptedSubmissions, $this->report->getAcceptedCount());
        $this->assertEquals($this->acceptedSubmissionsWithDataset, $this->report->getAcceptedWithDatasetCount());
        $this->assertEquals($this->declinedSubmissions, $this->report->getDeclinedCount());
        $this->assertEquals($this->declinedSubmissionsWithDataset, $this->report->getDeclinedWithDatasetCount());
        $this->assertEquals($this->datasetsWithDepositError, $this->report->getWithDepositErrorCount());
        $this->assertEquals($this->datasetsWithPublishError, $this->report->getWithPublishErrorCount());
        $this->assertEquals($this->filesInDatasets, $this->report->getDatasetFilesCount());
    }

    public function testReportHasExpectedHeaders(): void
    {
        $expectedOjsHeaders = $this->getExpectedHeaders('ojs2');
        $this->assertEquals($expectedOjsHeaders, $this->report->getHeaders());

        $this->report = $this->createTestReport('ops', $this->locale);
        $expectedOpsHeaders = $this->getExpectedHeaders('ops');
        $this->assertEquals($expectedOpsHeaders, $this->report->getHeaders());
    }

    public function testReportWritesToCsvFile(): void
    {
        $csvFilePath = '/tmp/dataverse_stats_report_test.csv';
        $this->report->writeReport($csvFilePath);

        $this->assertFileExists($csvFilePath);
        $csvFile = fopen($csvFilePath, 'r');
        $UTF8_BOM = chr(0xEF) . chr(0xBB) . chr(0xBF);
        fread($csvFile, strlen($UTF8_BOM));

        $expectedHeader = $this->getExpectedHeaders($this->application);
        $row = fgetcsv($csvFile);
        $this->assertEquals($expectedHeader, $row);

        $expectedStatsLine = [
            $this->acceptedSubmissions,
            $this->acceptedSubmissionsWithDataset,
            $this->declinedSubmissions,
            $this->declinedSubmissionsWithDataset,
            $this->datasetsWithDepositError,
            $this->datasetsWithPublishError,
            $this->filesInDatasets
        ];
        $row = fgetcsv($csvFile);
        $this->assertEquals($expectedStatsLine, $row);

        fclose($csvFile);
        unlink($csvFilePath);
    }
}
