<?php

namespace APP\plugins\generic\dataverse\report;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;

class DataverseReportForm extends Form
{
    private $plugin;
    private $contextId;
    private $application;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
        $this->application = substr(Application::getName(), 0, 3);
        $request = Application::get()->getRequest();
        $this->contextId = $request->getContext()->getId();

        parent::__construct($plugin->getTemplateResource('dataverseReport.tpl'));

        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    public function validateReportData($reportParams)
    {
        return true;
    }

    public function display($request = null, $template = null, $args = null)
    {
        $yearFirstDate = $args[0];
        $todayDate = $args[1];

        $templateManager = TemplateManager::getManager();
        $url = $request->getBaseUrl() . '/' . $this->plugin->getPluginPath() . '/styles/dataverseReport.css';
        $templateManager->addStyleSheet('dataverseReportStyleSheet', $url, [
            'priority' => TemplateManager::STYLE_SEQUENCE_CORE,
            'contexts' => 'backend',
        ]);

        $templateManager->assign('years', [$yearFirstDate, $todayDate]);
        $templateManager->assign([
            'breadcrumbs' => [
                [
                    'id' => 'reports',
                    'name' => __('manager.statistics.reports'),
                    'url' => $request->getRouter()->url($request, null, 'stats', 'reports'),
                ],
                [
                    'id' => 'dataverseReport',
                    'name' => __('plugins.generic.dataverse.report.displayName')
                ],
            ],
            'pageTitle',
            __('plugins.generic.dataverse.report.displayName')
        ]);

        $templateManager->display($this->plugin->getTemplateResource($template));
    }

    // public function generateReport($args, $request)
    // {
    //     $context = $request->getContext();

    //     $reportService = new DataverseReportService();

    //     $overview = $reportService->getOverview($context->getId());

    //     header('content-type: text/comma-separated-values');
    //     header('content-disposition: attachment; filename=dataverse-' . date('Ymd') . '.csv');
    //     $fp = fopen('php://output', 'wt');
    //     fputcsv($fp, $reportService->getReportHeaders());
    //     fputcsv($fp, $overview);
    //     fclose($fp);
    // }
}
