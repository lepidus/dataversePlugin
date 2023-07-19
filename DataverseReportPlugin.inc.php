<?php

import('lib.pkp.classes.plugins.ReportPlugin');
import('plugins.generic.dataverse.classes.services.queryBuilders.DataverseReportQueryBuilder');

class DataverseReportPlugin extends ReportPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if ($success && Config::getVar('general', 'installed')) {
            $this->addLocaleData();
        }
        return $success;
    }

    public function getName()
    {
        return 'dataverseReportPlugin';
    }

    public function getDisplayName()
    {
        return __('plugins.generic.dataverse.report.displayName');
    }

    public function getDescription()
    {
        return __('plugins.generic.dataverse.report.description');
    }

    public function display($args, $request)
    {
        $context = $request->getContext();

        import('plugins.generic.dataverse.classes.services.DataverseReportService');
        $reportService = new DataverseReportService();

        $params = [
            'acceptedSubmissions' => $reportService->countSubmissions([
                'contextIds' => [$context->getId()],
                'decisions' => [SUBMISSION_EDITOR_DECISION_ACCEPT]
            ]),
            'acceptedSubmissionsWithDataset' => $reportService->countSubmissionsWithDataset([
                'contextIds' => [$context->getId()],
                'decisions' => [SUBMISSION_EDITOR_DECISION_ACCEPT]
            ]),
            'declinedSubmissions' => $reportService->countSubmissions([
                'contextIds' => [$context->getId()],
                'decisions' => [SUBMISSION_EDITOR_DECISION_DECLINE, SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE]
            ]),
            'declinedSubmissionsWithDataset' => $reportService->countSubmissionsWithDataset([
                'contextIds' => [$context->getId()],
                'decisions' => [SUBMISSION_EDITOR_DECISION_DECLINE, SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE]
            ]),
        ];

        header('content-type: text/comma-separated-values');
        header('content-disposition: attachment; filename=dataverse-' . date('Ymd') . '.csv');
        $fp = fopen('php://output', 'wt');
        fputcsv($fp, $reportService->getReportHeaders());
        fputcsv($fp, $params);
        fclose($fp);
    }
}
