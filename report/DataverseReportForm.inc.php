<?php

import('lib.pkp.classes.form.Form');
// import('plugins.generic.dataverse.report.classes.DataverseStatsReportBuilder');

class DataverseReportForm extends Form
{
    private const DAY_BEGINNING = ' 00:00:00';
    private const DAY_ENDING = ' 23:59:59';

    private $plugin;
    private $contextId;
    private $application;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
        $this->application = Application::getName();
        $request = Application::get()->getRequest();
        $this->contextId = $request->getContext()->getId();

        parent::__construct($plugin->getTemplateResource('dataverseReport.tpl'));

        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    public function readInputData()
    {
        $this->readUserVars([
            'selectFilterTypeDate',
            'startSubmissionDateInterval',
            'endSubmissionDateInterval',
            'startFinalDecisionDateInterval',
            'endFinalDecisionDateInterval'
        ]);
    }

    public function validateReportData()
    {
        $selectedFilter = $this->getData('selectFilterTypeDate');
        $startDate = $endDate = '';

        if ($selectedFilter == 'filterBySubmission') {
            $startDate = $this->getData('startSubmissionDateInterval');
            $endDate = $this->getData('endSubmissionDateInterval');
        } elseif ($selectedFilter == 'filterByFinalDecision') {
            $startDate = $this->getData('startFinalDecisionDateInterval');
            $endDate = $this->getData('endFinalDecisionDateInterval');
        } else {
            return false;
        }

        if ($startDate > $endDate) {
            return false;
        }

        return true;
    }

    public function display($request = null, $template = null, $args = null)
    {
        $yearFirstDate = $args[0];
        $todayDate = $args[1];

        $templateManager = TemplateManager::getManager();
        $url = $request->getBaseUrl() . '/' . $this->plugin->getPluginPath() . '/styles/dataverseReport.css';
        $templateManager->addStyleSheet('dataverseReportStyleSheet', $url, [
            'priority' => STYLE_SEQUENCE_CORE,
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

    public function generateReport()
    {
        $reportBuilder = new DataverseStatsReportBuilder();
        $selectedFilter = $this->getData('selectFilterTypeDate');

        if ($selectedFilter == 'filterBySubmission') {
            $reportBuilder->setDateSubmittedInterval(
                $this->getData('startSubmissionDateInterval') . self::DAY_BEGINNING,
                $this->getData('endSubmissionDateInterval') . self::DAY_ENDING
            );
        } elseif ($selectedFilter == 'filterByFinalDecision') {
            $reportBuilder->setFinalDecisionDateInterval(
                $this->getData('startFinalDecisionDateInterval') . self::DAY_BEGINNING,
                $this->getData('endFinalDecisionDateInterval') . self::DAY_ENDING
            );
        }
        $report = $reportBuilder->createReport($this->application, $this->contextId);

        header('content-type: text/comma-separated-values');
        header('content-disposition: attachment; filename=dataverse-' . date('Ymd') . '.csv');

        $report->writeReport('php://output');
    }
}
