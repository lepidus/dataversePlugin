<?php

use PKP\tests\PKPTestCase;
use APP\plugins\generic\dataverse\report\classes\DataverseStatsReport;
use APP\plugins\generic\dataverse\DataversePlugin;

class DataverseStatsReportTest extends PKPTestCase
{
    private DataverseStatsReport $report;
    private string $locale = 'en';
    private string $application = DataverseStatsReport::OJS_APP_NAME;
    private int $publishedSubmissions = 70;
    private int $publishedSubmissionsWithDataset = 45;
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
        $stats = [
            'declinedCount' => $this->declinedSubmissions,
            'declinedWithDatasetCount' => $this->declinedSubmissionsWithDataset,
            'withDepositErrorCount' => $this->datasetsWithDepositError,
            'withPublishErrorCount' => $this->datasetsWithPublishError,
            'datasetFilesCount' => $this->filesInDatasets,
        ];

        if ($application == DataverseStatsReport::OJS_APP_NAME) {
            $stats['acceptedCount'] = $this->acceptedSubmissions;
            $stats['acceptedWithDatasetCount'] = $this->acceptedSubmissionsWithDataset;
        } elseif ($application == DataverseStatsReport::OPS_APP_NAME) {
            $stats['publishedCount'] = $this->publishedSubmissions;
            $stats['publishedWithDatasetCount'] = $this->publishedSubmissionsWithDataset;
        }

        return new DataverseStatsReport($application, $locale, $stats);
    }

    private function getExpectedHeaders(string $application): array
    {
        $headers = [];

        if ($application == DataverseStatsReport::OJS_APP_NAME) {
            $headers = [
                __('plugins.generic.dataverse.report.headers.acceptedSubmissions'),
                __('plugins.generic.dataverse.report.headers.acceptedSubmissionsWithDataset'),
            ];
        } elseif ($application == DataverseStatsReport::OPS_APP_NAME) {
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

    public function testReportHasStatsDataForOjs(): void
    {
        $expectedStatsData = [
            $this->acceptedSubmissions,
            $this->acceptedSubmissionsWithDataset,
            $this->declinedSubmissions,
            $this->declinedSubmissionsWithDataset,
            $this->datasetsWithDepositError,
            $this->datasetsWithPublishError,
            $this->filesInDatasets
        ];

        $this->assertEquals($expectedStatsData, $this->report->getStatsData());
    }

    public function testReportHasStatsDataForOps(): void
    {
        $this->report = $this->createTestReport(DataverseStatsReport::OPS_APP_NAME, $this->locale);
        $expectedStatsData = [
            $this->publishedSubmissions,
            $this->publishedSubmissionsWithDataset,
            $this->declinedSubmissions,
            $this->declinedSubmissionsWithDataset,
            $this->datasetsWithDepositError,
            $this->datasetsWithPublishError,
            $this->filesInDatasets
        ];

        $this->assertEquals($expectedStatsData, $this->report->getStatsData());
    }

    public function testReportHasExpectedHeaders(): void
    {
        $expectedOjsHeaders = $this->getExpectedHeaders(DataverseStatsReport::OJS_APP_NAME);
        $this->assertEquals($expectedOjsHeaders, $this->report->getHeaders());

        $this->report = $this->createTestReport(DataverseStatsReport::OPS_APP_NAME, $this->locale);
        $expectedOpsHeaders = $this->getExpectedHeaders(DataverseStatsReport::OPS_APP_NAME);
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
