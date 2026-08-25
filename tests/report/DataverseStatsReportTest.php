<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.services.DataStatementService');
import('plugins.generic.dataverse.report.classes.DataStatementStats');
import('plugins.generic.dataverse.report.classes.DataverseStatsReport');
import('plugins.generic.dataverse.DataversePlugin');

class DataverseStatsReportTest extends PKPTestCase
{
    private DataverseStatsReport $report;
    private string $locale = 'en_US';
    private string $application = DataverseStatsReport::OJS_APP_NAME;
    private int $publishedSubmissions = 70;
    private int $acceptedSubmissions = 40;
    private int $declinedSubmissions = 200;
    private int $totalSubmissions = 300;
    private int $publishedSubmissionsWithDataset = 45;
    private int $acceptedSubmissionsWithDataset = 35;
    private int $declinedSubmissionsWithDataset = 50;
    private int $datasetsWithDepositError = 25;
    private int $datasetsWithPublishError = 5;
    private int $filesInDatasets = 125;
    private array $dataStatementValues = [
        DATA_STATEMENT_TYPE_IN_MANUSCRIPT => 3,
        DATA_STATEMENT_TYPE_REPO_AVAILABLE => 7,
        DATA_STATEMENT_TYPE_ON_DEMAND => 15,
        DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE => 20
    ];

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
            'totalSubmissionsCount' => $this->totalSubmissions,
            'withDepositErrorCount' => $this->datasetsWithDepositError,
            'withPublishErrorCount' => $this->datasetsWithPublishError,
            'datasetFilesCount' => $this->filesInDatasets,
            'declinedStatementCount' => (new DataStatementStats($this->dataStatementValues)),
            'totalStatementCount' => (new DataStatementStats($this->dataStatementValues))
        ];

        if ($application == DataverseStatsReport::OJS_APP_NAME) {
            $stats['acceptedCount'] = $this->acceptedSubmissions;
            $stats['acceptedWithDatasetCount'] = $this->acceptedSubmissionsWithDataset;
            $stats['acceptedStatementCount'] = (new DataStatementStats($this->dataStatementValues));
        } elseif ($application == DataverseStatsReport::OPS_APP_NAME) {
            $stats['publishedCount'] = $this->publishedSubmissions;
            $stats['publishedWithDatasetCount'] = $this->publishedSubmissionsWithDataset;
            $stats['publishedStatementCount'] = (new DataStatementStats($this->dataStatementValues));
        }

        return new DataverseStatsReport($application, $locale, $stats);
    }

    private function getExpectedHeaders(string $application): array
    {
        $pubOrAcpt = ($application == DataverseStatsReport::OPS_APP_NAME)
            ? 'published'
            : 'accepted';
        $statementSectionColumns = [
            __('plugins.generic.dataverse.report.headers.statement.inManuscript'),
            __('plugins.generic.dataverse.report.headers.statement.repoAvailable'),
            __('plugins.generic.dataverse.report.headers.statement.onDemand'),
            __('plugins.generic.dataverse.report.headers.statement.publiclyUnavailable')
        ];

        $firstHeadersLine = [
            __('navigation.submissions'),
            '',
            '',
            __('plugins.generic.dataverse.report.headers.submissionsWithDataset'),
            '',
            __("plugins.generic.dataverse.report.headers.statement.$pubOrAcpt"),
            '',
            '',
            '',
            __('plugins.generic.dataverse.report.headers.statement.declined'),
            '',
            '',
            '',
            __('plugins.generic.dataverse.report.headers.statement.total'),
            '',
            '',
            '',
            __('plugins.generic.dataverse.report.headers.datasetsMetrics'),
            '',
            '',
        ];

        $secondHeadersLine = [
            __("plugins.generic.dataverse.report.headers.{$pubOrAcpt}Submissions"),
            __('plugins.generic.dataverse.report.headers.declinedSubmissions'),
            __('plugins.generic.dataverse.report.headers.totalSubmissions'),
            __("plugins.generic.dataverse.report.headers.{$pubOrAcpt}SubmissionsWithDataset"),
            __('plugins.generic.dataverse.report.headers.declinedSubmissionsWithDataset'),
            ...$statementSectionColumns,
            ...$statementSectionColumns,
            ...$statementSectionColumns,
            __('plugins.generic.dataverse.report.headers.datasetsWithDepositError'),
            __('plugins.generic.dataverse.report.headers.datasetsWithPublishError'),
            __('plugins.generic.dataverse.report.headers.filesInDatasets')
        ];

        return [
            $firstHeadersLine,
            $secondHeadersLine
        ];
    }

    public function testReportHasStatsDataForOjs(): void
    {
        $expectedStatsData = [
            $this->acceptedSubmissions,
            $this->declinedSubmissions,
            $this->totalSubmissions,
            $this->acceptedSubmissionsWithDataset,
            $this->declinedSubmissionsWithDataset,
            ...array_values($this->dataStatementValues),
            ...array_values($this->dataStatementValues),
            ...array_values($this->dataStatementValues),
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
            $this->declinedSubmissions,
            $this->totalSubmissions,
            $this->publishedSubmissionsWithDataset,
            $this->declinedSubmissionsWithDataset,
            ...array_values($this->dataStatementValues),
            ...array_values($this->dataStatementValues),
            ...array_values($this->dataStatementValues),
            $this->datasetsWithDepositError,
            $this->datasetsWithPublishError,
            $this->filesInDatasets
        ];

        $this->assertEquals($expectedStatsData, $this->report->getStatsData());
    }

    public function testReportHasExpectedHeaders(): void
    {
        $expectedOjsHeaders = $this->getExpectedHeaders(DataverseStatsReport::OJS_APP_NAME);
        $ojsHeaders = $this->report->getHeaders();
        $this->assertEquals($expectedOjsHeaders[0], $ojsHeaders[0]);
        $this->assertEquals($expectedOjsHeaders[1], $ojsHeaders[1]);

        $this->report = $this->createTestReport(DataverseStatsReport::OPS_APP_NAME, $this->locale);
        $expectedOpsHeaders = $this->getExpectedHeaders(DataverseStatsReport::OPS_APP_NAME);
        $opsHeaders = $this->report->getHeaders();
        $this->assertEquals($expectedOpsHeaders[0], $opsHeaders[0]);
        $this->assertEquals($expectedOpsHeaders[1], $opsHeaders[1]);
    }

    public function testReportWritesToCsvFile(): void
    {
        $csvFilePath = '/tmp/dataverse_stats_report_test.csv';
        $this->report->writeReport($csvFilePath);

        $this->assertFileExists($csvFilePath);
        $csvFile = fopen($csvFilePath, 'r');
        $UTF8_BOM = chr(0xEF) . chr(0xBB) . chr(0xBF);
        fread($csvFile, strlen($UTF8_BOM));

        $expectedHeaders = $this->getExpectedHeaders($this->application);
        $row = fgetcsv($csvFile);
        $this->assertEquals($expectedHeaders[0], $row);
        $row = fgetcsv($csvFile);
        $this->assertEquals($expectedHeaders[1], $row);

        $expectedStatsLine = [
            $this->acceptedSubmissions,
            $this->declinedSubmissions,
            $this->totalSubmissions,
            $this->acceptedSubmissionsWithDataset,
            $this->declinedSubmissionsWithDataset,
            ...array_values($this->dataStatementValues),
            ...array_values($this->dataStatementValues),
            ...array_values($this->dataStatementValues),
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
