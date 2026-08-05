<?php

use PKP\tests\PKPTestCase;
use APP\plugins\generic\dataverse\report\classes\DataverseStatsReport;
use APP\plugins\generic\dataverse\DataversePlugin;

class DataverseStatsReportTest extends PKPTestCase
{
    private DataverseStatsReport $report;
    private string $locale = 'en';
    private string $application = 'ojs2';

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

        // TODO: add the remaining data

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

    // TODO: Assertion of class data

    public function testReportHasExpectedHeaders(): void
    {
        $expectedOjsHeaders = $this->getExpectedHeaders('ojs2');
        $this->assertEquals($expectedOjsHeaders, $this->report->getHeaders());
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

        fclose($csvFile);
        unlink($csvFilePath);
    }
}
