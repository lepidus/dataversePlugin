<?php

namespace APP\plugins\generic\dataverse\report;

use PKP\plugins\ReportPlugin;
use PKP\config\Config;
use APP\plugins\generic\dataverse\report\services\queryBuilders\DataverseReportQueryBuilder;
use APP\plugins\generic\dataverse\report\services\DataverseReportService;

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

        $reportService = new DataverseReportService();

        $overview = $reportService->getOverview($context->getId());

        header('content-type: text/comma-separated-values');
        header('content-disposition: attachment; filename=dataverse-' . date('Ymd') . '.csv');
        $fp = fopen('php://output', 'wt');
        fputcsv($fp, $reportService->getReportHeaders());
        fputcsv($fp, $overview);
        fclose($fp);
    }
}
