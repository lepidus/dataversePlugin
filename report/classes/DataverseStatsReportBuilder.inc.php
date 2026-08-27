<?php

import('plugins.generic.dataverse.report.classes.DataverseStatsReport');
import('plugins.generic.dataverse.report.services.DataverseReportService');

class DataverseStatsReportBuilder
{
    private $beginSubmissionInterval = '';
    private $endSubmissionInterval = '';
    private $beginFinalDecisionInterval = '';
    private $endFinalDecisionInterval = '';

    public function createReport(string $applicationName, int $contextId): DataverseStatsReport
    {
        $locale = AppLocale::getLocale();
        $reportService = new DataverseReportService($contextId, $applicationName);

        if (!empty($this->beginSubmissionInterval) && !empty($this->endSubmissionInterval)) {
            $reportService->setDateSubmittedInterval($this->beginSubmissionInterval, $this->endSubmissionInterval);
        }
        if (!empty($this->beginFinalDecisionInterval) && !empty($this->endFinalDecisionInterval)) {
            $reportService->setFinalDecisionDateInterval(
                $this->beginFinalDecisionInterval,
                $this->endFinalDecisionInterval
            );
        }

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

    public function setDateSubmittedInterval(string $beginDate, string $endDate)
    {
        $this->beginSubmissionInterval = $beginDate;
        $this->endSubmissionInterval = $endDate;
    }

    public function setFinalDecisionDateInterval(string $beginDate, string $endDate)
    {
        $this->beginFinalDecisionInterval = $beginDate;
        $this->endFinalDecisionInterval = $endDate;
    }
}
