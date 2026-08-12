<?php

namespace APP\plugins\generic\dataverse\report\classes;

use PKP\facades\Locale;
use APP\plugins\generic\dataverse\report\classes\DataverseStatsReport;
use APP\plugins\generic\dataverse\report\services\DataverseReportService;

class DataverseStatsReportBuilder
{
    public function createReport(string $applicationName, int $contextId): DataverseStatsReport
    {
        $locale = Locale::getLocale();
        $reportService = new DataverseReportService($contextId);
        $stats = [];

        if ($applicationName == DataverseStatsReport::OJS_APP_NAME) {
            $stats['acceptedCount'] = $reportService->getAcceptedSubmissionsCount();
            $stats['acceptedWithDatasetCount'] = $reportService->getAcceptedSubmissionsWithDatasetCount();
            $stats['acceptedStatementCount'] = $reportService->getAcceptedStatementStatistics();
        } elseif ($applicationName == DataverseStatsReport::OPS_APP_NAME) {
            $stats['publishedCount'] = $reportService->getPublishedSubmissionsCount();
            $stats['publishedWithDatasetCount'] = $reportService->getPublishedSubmissionsWithDatasetCount();
            $stats['publishedStatementCount'] = $reportService->getPublishedStatementStatistics();
        }

        $stats['declinedCount'] = $reportService->getDeclinedSubmissionsCount();
        $stats['declinedWithDatasetCount'] = $reportService->getDeclinedSubmissionsWithDatasetCount();
        $stats['totalSubmissionsCount'] = $reportService->getTotalSubmissionsCount();
        $stats['withDepositErrorCount'] = $reportService->getDatasetsWithDepositErrorCount();
        $stats['withPublishErrorCount'] = $reportService->getDatasetsWithPublishErrorCount();
        $stats['datasetFilesCount'] = $reportService->countDatasetFiles();
        $stats['declinedStatementCount'] = $reportService->getDeclinedStatementStatistics();
        $stats['totalStatementCount'] = $reportService->getTotalStatementStatistics();

        return new DataverseStatsReport($applicationName, $locale, $stats);
    }
}
