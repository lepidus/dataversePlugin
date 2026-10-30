<?php

namespace APP\plugins\generic\dataverse\report;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;
use APP\plugins\generic\dataverse\report\classes\DataverseStatsReportBuilder;

class DataverseReportForm extends Form
{
    public const FILTER_BY_SUBMISSION = 'filterBySubmission';
    public const FILTER_BY_FINAL_DECISION = 'filterByFinalDecision';

    private const DAY_BEGINNING = ' 00:00:00';
    private const DAY_ENDING = ' 23:59:59';
    private const DATE_FIELDS = [
        self::FILTER_BY_SUBMISSION => ['startSubmissionDateInterval', 'endSubmissionDateInterval'],
        self::FILTER_BY_FINAL_DECISION => ['startFinalDecisionDateInterval', 'endFinalDecisionDateInterval'],
    ];

    public function __construct(
        private DataverseReportPlugin $plugin,
        private int $contextId
    ) {
        parent::__construct($plugin->getTemplateResource('dataverseReport.tpl'));

        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    public function initData()
    {
        $this->setData('selectFilterTypeDate', self::FILTER_BY_SUBMISSION);
        foreach (self::DATE_FIELDS as [$startField, $endField]) {
            $this->setData($startField, date('Y-01-01'));
            $this->setData($endField, date('Y-m-d'));
        }
    }

    public function readInputData()
    {
        $this->readUserVars(['selectFilterTypeDate', ...array_merge(...array_values(self::DATE_FIELDS))]);
    }

    public function validate($callHooks = true)
    {
        $selectedFilter = $this->getData('selectFilterTypeDate');

        if (!isset(self::DATE_FIELDS[$selectedFilter])) {
            $this->addError('selectFilterTypeDate', __('validator.required'));
        } else {
            $this->validatePeriod(...self::DATE_FIELDS[$selectedFilter]);
        }

        return parent::validate($callHooks);
    }

    public function display($request = null, $template = null)
    {
        $templateManager = TemplateManager::getManager($request);
        $templateManager->addStyleSheet(
            'dataverseReportStyleSheet',
            $request->getBaseUrl() . '/' . $this->plugin->getPluginPath() . '/styles/dataverseReport.css',
            ['contexts' => 'backend']
        );
        $templateManager->addJavaScript(
            'dataverseReportFilterToggle',
            $this->getFilterToggleScript(),
            ['inline' => true, 'contexts' => 'backend']
        );
        $templateManager->assign([
            'filterBySubmission' => self::FILTER_BY_SUBMISSION,
            'filterByFinalDecision' => self::FILTER_BY_FINAL_DECISION,
            'breadcrumbs' => [
                [
                    'id' => 'reports',
                    'name' => __('manager.statistics.reports'),
                    'url' => $request->getRouter()->url($request, null, 'stats', 'reports'),
                ],
                [
                    'id' => 'dataverseReport',
                    'name' => __('plugins.generic.dataverse.report.displayName'),
                ],
            ],
            'pageTitle' => __('plugins.generic.dataverse.report.displayName'),
        ]);

        parent::display($request, $template);
    }

    public function execute(...$functionArgs)
    {
        $reportBuilder = new DataverseStatsReportBuilder();
        $selectedFilter = $this->getData('selectFilterTypeDate');
        [$startField, $endField] = self::DATE_FIELDS[$selectedFilter];
        $beginning = $this->getData($startField) . self::DAY_BEGINNING;
        $ending = $this->getData($endField) . self::DAY_ENDING;

        if ($selectedFilter == self::FILTER_BY_SUBMISSION) {
            $reportBuilder->setDateSubmittedInterval($beginning, $ending);
        } else {
            $reportBuilder->setFinalDecisionDateInterval($beginning, $ending);
        }

        $report = $reportBuilder->createReport(Application::get()->getName(), $this->contextId);

        header('content-type: text/comma-separated-values');
        header('content-disposition: attachment; filename=dataverse-' . date('Ymd') . '.csv');
        $report->writeReport('php://output');

        parent::execute(...$functionArgs);
    }

    private function getFilterToggleScript(): string
    {
        $filterBySubmission = json_encode(self::FILTER_BY_SUBMISSION);
        $filterByFinalDecision = json_encode(self::FILTER_BY_FINAL_DECISION);

        return <<<JS
            document.addEventListener('change', function (event) {
                if (event.target.id !== 'selectFilterTypeDate') {
                    return;
                }
                document.getElementById('submittedDateFields').hidden = event.target.value !== {$filterBySubmission};
                document.getElementById('finalDecisionDateFields').hidden = event.target.value !== {$filterByFinalDecision};
            });
            JS;
    }

    private function validatePeriod(string $startField, string $endField): void
    {
        $startDate = $this->getData($startField);
        $endDate = $this->getData($endField);

        if (!$this->isValidDate($startDate) || !$this->isValidDate($endDate)) {
            $this->addError($startField, __('stats.dateRange.invalidDate'));
        } elseif ($startDate > $endDate) {
            $this->addError($startField, __('stats.dateRange.invalidDateRange'));
        }
    }

    private function isValidDate(?string $date): bool
    {
        $parsedDate = \DateTime::createFromFormat('Y-m-d', (string) $date);

        return $parsedDate && $parsedDate->format('Y-m-d') === $date;
    }
}
