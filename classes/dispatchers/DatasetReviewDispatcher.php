<?php

namespace APP\plugins\generic\dataverse\classes\dispatchers;

use PKP\plugins\Hook;
use APP\core\Application;
use APP\template\TemplateManager;
use APP\plugins\generic\dataverse\classes\dispatchers\DataverseDispatcher;
use APP\plugins\generic\dataverse\classes\services\DataStatementService;

class DatasetReviewDispatcher extends DataverseDispatcher
{
    protected function registerHooks(): void
    {
        Hook::add('pkpreviewerreviewstep1form::display', [$this, 'addResearchDataToReviewStep']);
        Hook::add('reviewerreviewstep3form::display', [$this, 'addResearchDataToReviewStep']);
    }

    public function addResearchDataToReviewStep(string $hookName, array $params)
    {
        $form = $params[0];
        $request = Application::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);

        $submission = $form->getReviewSubmission();
        $mapStepPattern = [
            'pkpreviewerreviewstep1form::display' => '/<div[^>]+class="pkp_linkActions[^>]+>/',
            'reviewerreviewstep3form::display' => '/<div[^>]+class="section[^>]+>/'
        ];

        $templateMgr->assign([
            'allDataStatementTypes' => $this->getDataStatementTypes(),
            'publication' => $submission->getCurrentPublication(),
            'reviewStepPattern' => $mapStepPattern[$hookName]
        ]);
        $templateMgr->registerFilter("output", [$this, 'addResearchDataToReviewStepFilter']);
    }

    public function addResearchDataToReviewStepFilter($output, $templateMgr)
    {
        $reviewStepPattern = $templateMgr->getTemplateVars('reviewStepPattern');
        $step = $templateMgr->getTemplateVars('step');

        if (
            preg_match('/id="reviewStep' . $step . 'Form"/', $output)
            && preg_match($reviewStepPattern, $output, $matches, PREG_OFFSET_CAPTURE)
        ) {
            $posMatch = $matches[0][1];

            $datasetReviewOutput = $templateMgr->fetch($this->plugin->getTemplateResource('datasetReview.tpl'));

            $output = substr_replace($output, $datasetReviewOutput, $posMatch, 0);
            $templateMgr->unregisterFilter('output', array($this, 'addResearchDataToReviewStepFilter'));
        }

        return $output;
    }

    private function getDataStatementTypes(): array
    {
        $dataStatementService = new DataStatementService();
        $allDataStatementTypes = $dataStatementService->getDataStatementTypes();
        $dataverseName = $dataStatementService->getDataverseName();

        $allDataStatementTypes[DataStatementService::DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED] = __('plugins.generic.dataverse.dataStatement.researchDataSubmitted', ['dataverseName' => $dataverseName]);

        return $allDataStatementTypes;
    }
}
