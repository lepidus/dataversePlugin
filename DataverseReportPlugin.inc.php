<?php

import('lib.pkp.classes.plugins.ReportPlugin');

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
        return __('plugins.reports.dataverse.displayName');
    }

    public function getDescription()
    {
        return __('plugins.reports.dataverse.description');
    }

    public function display($args, $request)
    {
        header('content-type: text/comma-separated-values');
        header('content-disposition: attachment; filename=dataverse-' . date('Ymd') . '.csv');
        $fp = fopen('php://output', 'wt');
        fclose($fp);
    }
}
