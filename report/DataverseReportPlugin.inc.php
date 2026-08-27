<?php

import('lib.pkp.classes.plugins.ReportPlugin');
import('plugins.generic.dataverse.report.DataverseReportForm');

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
        $form = new DataverseReportForm($this);
        $form->initData();
        if ($request->isPost($request)) {
            $form->readInputData();
            if ($form->validateReportData()) {
                $form->generateReport();
            }
        } else {
            $dateStart = date('Y-01-01');
            $dateEnd = date('Y-m-d');
            $form->display($request, 'dataverseReport.tpl', [$dateStart, $dateEnd]);
        }
    }
}
