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
        $report = new DataverseStatsReport($applicationName, $locale);

        if ($applicationName == DataverseStatsReport::OJS_APP_NAME) {
            $report->setAcceptedCount($reportService->getAcceptedSubmissionsCount());
            $report->setAcceptedWithDatasetCount($reportService->getAcceptedSubmissionsWithDatasetCount());
        }

        $report->setDeclinedCount($reportService->getDeclinedSubmissionsCount());
        $report->setDeclinedWithDatasetCount($reportService->getDeclinedSubmissionsWithDatasetCount());
        $report->setWithDepositErrorCount($reportService->getDatasetsWithDepositErrorCount());
        $report->setWithPublishErrorCount($reportService->getDatasetsWithPublishErrorCount());
        $report->setDatasetFilesCount($reportService->countDatasetFiles());

        return $report;
    }
}
