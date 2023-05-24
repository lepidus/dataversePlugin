<?php

import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');

class DatasetReviewDispatcher extends DataverseDispatcher
{
    protected function registerHooks(): void
    {
        HookRegistry::register('pkpreviewerreviewstep1form::display', array($this, 'addResearchDataToReviewStep1'));
        HookRegistry::register('reviewerreviewstep3form::display', array($this, 'addResearchDataToReviewStep3'));
    }

    public function addResearchDataToReviewStep1(string $hookName, array $params): ?string
    {
        $pattern = '/<div[^>]+class="pkp_linkActions[^>]+>/';

        return $this->addResearchDataToReviewStep($params, $pattern);
    }

    public function addResearchDataToReviewStep3(string $hookName, array $params): ?string
    {
        $pattern = '/<div[^>]+class="section[^>]+>/';

        return $this->addResearchDataToReviewStep($params, $pattern);
    }

    private function addResearchDataToReviewStep($params, $pattern): ?string
    {
        $form = $params[0];
        $output =& $params[1];
        $submission = $form->getReviewerSubmission();

        $request = PKPApplication::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);

        $templateOutput = $templateMgr->fetch($form->_template);
        if (preg_match($pattern, $templateOutput, $matches, PREG_OFFSET_CAPTURE)) {
            $offset = $matches[0][1];
            $output = substr($templateOutput, 0, $offset);
            $output .= $templateMgr->fetch($this->plugin->getTemplateResource('datasetReview.tpl'));
            $output .= substr($templateOutput, $offset);
        }

        return $output;
    }
}
