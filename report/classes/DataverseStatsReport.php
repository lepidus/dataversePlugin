<?php

namespace APP\plugins\generic\dataverse\report\classes;

class DataverseStatsReport
{
    private const OJS_APP_NAME = 'ojs2';

    private $UTF8_BOM;
    private int $acceptedCount;
    private int $acceptedWithDatasetCount;
    private int $declinedCount;
    private int $declinedWithDatasetCount;
    private int $withDepositErrorCount;
    private int $withPublishErrorCount;
    private int $datasetFilesCount;

    public function __construct(
        private string $application,
        private string $locale
    ) {
        $this->UTF8_BOM = chr(0xEF) . chr(0xBB) . chr(0xBF);
    }

    public function getHeaders(): array
    {
        $headers = [];

        if ($this->application == self::OJS_APP_NAME) {
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

    public function setAcceptedCount(int $acceptedCount): void
    {
        $this->acceptedCount = $acceptedCount;
    }

    public function getAcceptedCount(): int
    {
        return $this->acceptedCount;
    }

    public function setAcceptedWithDatasetCount(int $acceptedWithDatasetCount): void
    {
        $this->acceptedWithDatasetCount = $acceptedWithDatasetCount;
    }

    public function getAcceptedWithDatasetCount(): int
    {
        return $this->acceptedWithDatasetCount;
    }

    public function setDeclinedCount(int $declinedCount): void
    {
        $this->declinedCount = $declinedCount;
    }

    public function getDeclinedCount(): int
    {
        return $this->declinedCount;
    }

    public function setDeclinedWithDatasetCount(int $declinedWithDatasetCount): void
    {
        $this->declinedWithDatasetCount = $declinedWithDatasetCount;
    }

    public function getDeclinedWithDatasetCount(): int
    {
        return $this->declinedWithDatasetCount;
    }

    public function setWithDepositErrorCount(int $withDepositErrorCount): void
    {
        $this->withDepositErrorCount = $withDepositErrorCount;
    }

    public function getWithDepositErrorCount(): int
    {
        return $this->withDepositErrorCount;
    }

    public function setWithPublishErrorCount(int $withPublishErrorCount): void
    {
        $this->withPublishErrorCount = $withPublishErrorCount;
    }

    public function getWithPublishErrorCount(): int
    {
        return $this->withPublishErrorCount;
    }

    public function setDatasetFilesCount(int $datasetFilesCount): void
    {
        $this->datasetFilesCount = $datasetFilesCount;
    }

    public function getDatasetFilesCount(): int
    {
        return $this->datasetFilesCount;
    }

    public function getStatsData(): array
    {
        $statsData = [];

        if ($this->application == self::OJS_APP_NAME) {
            $statsData = [
                $this->acceptedCount,
                $this->acceptedWithDatasetCount,
            ];
        }

        return array_merge(
            $statsData,
            [
                $this->declinedCount,
                $this->declinedWithDatasetCount,
                $this->withDepositErrorCount,
                $this->withPublishErrorCount,
                $this->datasetFilesCount
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
