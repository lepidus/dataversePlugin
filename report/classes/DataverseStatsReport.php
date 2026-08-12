<?php

namespace APP\plugins\generic\dataverse\report\classes;

class DataverseStatsReport
{
    public const OJS_APP_NAME = 'ojs2';
    public const OPS_APP_NAME = 'ops';

    private $UTF8_BOM;

    public function __construct(
        private string $application,
        private string $locale,
        private array $stats
    ) {
        $this->UTF8_BOM = chr(0xEF) . chr(0xBB) . chr(0xBF);
    }

    public function getHeaders(): array
    {
        $pubOrAcpt = ($this->application == DataverseStatsReport::OPS_APP_NAME)
            ? 'published'
            : 'accepted';
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

        $secondHeadersLine = [
            __("plugins.generic.dataverse.report.headers.{$pubOrAcpt}Submissions", [], $this->locale),
            __('plugins.generic.dataverse.report.headers.declinedSubmissions', [], $this->locale),
            __('plugins.generic.dataverse.report.headers.totalSubmissions', [], $this->locale),
            __("plugins.generic.dataverse.report.headers.{$pubOrAcpt}SubmissionsWithDataset",  [], $this->locale),
            __('plugins.generic.dataverse.report.headers.declinedSubmissionsWithDataset', [], $this->locale),
            ...$statementSectionColumns,
            ...$statementSectionColumns,
            ...$statementSectionColumns,
            __('plugins.generic.dataverse.report.headers.datasetsWithDepositError', [], $this->locale),
            __('plugins.generic.dataverse.report.headers.datasetsWithPublishError', [], $this->locale),
            __('plugins.generic.dataverse.report.headers.filesInDatasets', [], $this->locale)
        ];

        return [
            $firstHeadersLine,
            $secondHeadersLine
        ];
    }

    public function getStatsData(): array
    {
        $statsData = [];

        if ($this->application == self::OJS_APP_NAME) {
            $statsData = [
                $this->stats['acceptedCount'],
                $this->stats['acceptedWithDatasetCount'],
            ];
        } elseif ($this->application == self::OPS_APP_NAME) {
            $statsData = [
                $this->stats['publishedCount'],
                $this->stats['publishedWithDatasetCount'],
            ];
        }

        return array_merge(
            $statsData,
            [
                $this->stats['declinedCount'],
                $this->stats['declinedWithDatasetCount'],
                $this->stats['totalSubmissionsCount'],
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
