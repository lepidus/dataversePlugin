<?php

class DataverseStatsReport
{
    public const OJS_APP_NAME = 'ojs2';
    public const OPS_APP_NAME = 'ops';

    private $UTF8_BOM;
    private $application;
    private $locale;
    private $stats;

    public function __construct(string $application, string $locale, array $stats)
    {
        $this->application = $application;
        $this->locale = $locale;
        $this->stats = $stats;
        $this->UTF8_BOM = chr(0xEF) . chr(0xBB) . chr(0xBF);
    }

    private function getPubOrAcpt(): string
    {
        return $this->application == DataverseStatsReport::OPS_APP_NAME
            ? 'published'
            : 'accepted';
    }

    public function getHeaders(): array
    {
        $pubOrAcpt = $this->getPubOrAcpt();
        $statementSectionColumns = [
            __('plugins.generic.dataverse.report.headers.statement.inManuscript', [], $this->locale),
            __('plugins.generic.dataverse.report.headers.statement.repoAvailable', [], $this->locale),
            __('plugins.generic.dataverse.report.headers.statement.onDemand', [], $this->locale),
            __('plugins.generic.dataverse.report.headers.statement.publiclyUnavailable', [], $this->locale)
        ];

        $firstHeadersLine = [
            __('navigation.submissions', [], $this->locale),
            '',
            '',
            __('plugins.generic.dataverse.report.headers.submissionsWithDataset', [], $this->locale),
            '',
            __("plugins.generic.dataverse.report.headers.statement.$pubOrAcpt", [], $this->locale),
            '',
            '',
            '',
            __('plugins.generic.dataverse.report.headers.statement.declined', [], $this->locale),
            '',
            '',
            '',
            __('plugins.generic.dataverse.report.headers.statement.total', [], $this->locale),
            '',
            '',
            '',
            __('plugins.generic.dataverse.report.headers.datasetsMetrics', [], $this->locale),
            '',
            '',
        ];

        $secondHeadersLine = array_merge(
            [
                __("plugins.generic.dataverse.report.headers.{$pubOrAcpt}Submissions", [], $this->locale),
                __('plugins.generic.dataverse.report.headers.declinedSubmissions', [], $this->locale),
                __('plugins.generic.dataverse.report.headers.totalSubmissions', [], $this->locale),
                __("plugins.generic.dataverse.report.headers.{$pubOrAcpt}SubmissionsWithDataset", [], $this->locale),
                __('plugins.generic.dataverse.report.headers.declinedSubmissionsWithDataset', [], $this->locale),
            ],
            $statementSectionColumns,
            $statementSectionColumns,
            $statementSectionColumns,
            [
                __('plugins.generic.dataverse.report.headers.datasetsWithDepositError', [], $this->locale),
                __('plugins.generic.dataverse.report.headers.datasetsWithPublishError', [], $this->locale),
                __('plugins.generic.dataverse.report.headers.filesInDatasets', [], $this->locale)
            ]
        );

        return [
            $firstHeadersLine,
            $secondHeadersLine
        ];
    }

    public function getStatsData(): array
    {
        $pubOrAcpt = $this->getPubOrAcpt();

        return array_merge(
            [
                $this->stats["{$pubOrAcpt}Count"],
                $this->stats['declinedCount'],
                $this->stats['totalSubmissionsCount'],
                $this->stats["{$pubOrAcpt}WithDatasetCount"],
                $this->stats['declinedWithDatasetCount'],
            ],
            $this->stats["{$pubOrAcpt}StatementCount"]->getStats(),
            $this->stats["declinedStatementCount"]->getStats(),
            $this->stats["totalStatementCount"]->getStats(),
            [
                $this->stats['withDepositErrorCount'],
                $this->stats['withPublishErrorCount'],
                $this->stats['datasetFilesCount']
            ]
        );
    }

    public function writeReport(string $filePath)
    {
        $csvFile = fopen($filePath, 'wt');
        fprintf($csvFile, $this->UTF8_BOM);

        $reportHeaders = $this->getHeaders();
        fputcsv($csvFile, $reportHeaders[0]);
        fputcsv($csvFile, $reportHeaders[1]);
        fputcsv($csvFile, $this->getStatsData());

        fclose($csvFile);
    }
}
