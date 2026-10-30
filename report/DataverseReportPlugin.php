<?php

namespace APP\plugins\generic\dataverse\report;

use PKP\plugins\ReportPlugin;
use PKP\config\Config;

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
        $form = new DataverseReportForm($this, $request->getContext()->getId());
        $form->initData();

        if ($request->isPost()) {
            $form->readInputData();
            if ($form->validate()) {
                $form->execute();
                return;
            }
        }

        $form->display($request);
    }
}
