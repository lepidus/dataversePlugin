<?php

import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');

class DatasetReviewDispatcher extends DataverseDispatcher
{
    protected function registerHooks(): void
    {
        HookRegistry::register('pkpreviewerreviewstep1form::display', array($this, 'addResearchDataToReview'));
    }

    public function addResearchDataToReview(string $hookName, array $params): ?string
    {
        $form = $params[0];
        $output =& $params[1];
        $submission = $form->getReviewerSubmission();

        $request = PKPApplication::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);

        $templateOutput = $templateMgr->fetch($form->_template);
        $pattern = '/<div[^>]+class="pkp_linkActions[^>]+>/';
        if (preg_match($pattern, $templateOutput, $matches, PREG_OFFSET_CAPTURE)) {
            $offset = $matches[0][1];
            $output = substr($templateOutput, 0, $offset);
            $output .= $templateMgr->fetch($this->plugin->getTemplateResource('datasetDataReview.tpl'));
            $output .= substr($templateOutput, $offset);
        }

        return $output;
    }
}
