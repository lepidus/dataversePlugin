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
        $headers = [];

        if ($this->application == self::OJS_APP_NAME) {
            $headers = [
                __('plugins.generic.dataverse.report.headers.acceptedSubmissions', [], $this->locale),
                __('plugins.generic.dataverse.report.headers.acceptedSubmissionsWithDataset', [], $this->locale),
            ];
        } elseif ($this->application == self::OPS_APP_NAME) {
            $headers = [
                __('plugins.generic.dataverse.report.headers.publishedSubmissions', [], $this->locale),
                __('plugins.generic.dataverse.report.headers.publishedSubmissionsWithDataset', [], $this->locale),
            ];
        }

        return array_merge(
            $headers,
            [
                __('plugins.generic.dataverse.report.headers.declinedSubmissions', [], $this->locale),
                __('plugins.generic.dataverse.report.headers.declinedSubmissionsWithDataset', [], $this->locale),
                __('plugins.generic.dataverse.report.headers.datasetsWithDepositError', [], $this->locale),
                __('plugins.generic.dataverse.report.headers.datasetsWithPublishError', [], $this->locale),
                __('plugins.generic.dataverse.report.headers.filesInDatasets', [], $this->locale)
            ]
        );
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

        $reportHeader = $this->getHeaders();
        fputcsv($csvFile, $reportHeader);
        fputcsv($csvFile, $this->getStatsData());

        fclose($csvFile);
    }
}
