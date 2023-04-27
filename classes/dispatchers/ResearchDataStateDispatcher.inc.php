<?php

import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');
import('plugins.generic.dataverse.classes.services.ResearchDataStateService');

class ResearchDataStateDispatcher extends DataverseDispatcher
{
    public function registerHooks(): void
    {
        HookRegistry::register('TemplateManager::display', [$this, 'addResearchDataStateStyles']);
        HookRegistry::register('submissionsubmitstep1form::Constructor', [$this, 'addResearchDataStateValidate']);
        HookRegistry::register('submissionsubmitstep1form::display', [$this, 'addResearchDataStateField']);
        HookRegistry::register('Schema::get::publication', [$this, 'addResearchDataStatePropsToPublicationSchema']);
        HookRegistry::register('SubmissionHandler::saveSubmit', [$this, 'saveResearchDataState']);
    }

    public function addResearchDataStateStyles(string $hookName, array $args): bool
    {
        $templateMgr = $args[0];
        $template = $args[1];

        if ($template !== 'submission/form/index.tpl') {
            return false;
        }

        $templateMgr->addStyleSheet(
            'researchDataState',
            $this->plugin->getPluginFullPath() . '/styles/researchDataStates.css',
            ['contexts' => ['backend']]
        );

        return false;
    }

    public function addResearchDataStateValidate(string $hookName, array $args): bool
    {
        $form =& $args[0];

        $form->addCheck(new FormValidatorUrl(
            $form,
            'researchDataUrl',
            'optional',
            'plugins.generic.dataverse.researchDataState.repoAvailable.url.required'
        ));

        return false;
    }

    public function addResearchDataStateField(string $hookName, array $args): bool
    {
        $request = PKPApplication::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);

        $researchDataStateService = new ResearchDataStateService();

        $templateMgr->assign('researchDataStates', $researchDataStateService->getResearchDataStates());

        $templateMgr->registerFilter("output", array($this, 'researchDataStateFilter'));
        return false;
    }

    public function researchDataStateFilter(string $output, Smarty_Internal_Template $templateMgr)
    {
        if (preg_match('/<div[^>]+id="pkp_submissionChecklist/', $output, $matches, PREG_OFFSET_CAPTURE)) {
            $match = $matches[0][0];
            $posMatch = $matches[0][1];

            $researchDataStateTemplate = $templateMgr->fetch(
                $this->plugin->getTemplateResource('researchDataState.tpl')
            );

            $output = substr_replace($output, $researchDataStateTemplate, $posMatch, 0);
            $templateMgr->unregisterFilter('output', array($this, 'researchDataStateFilter'));
        }
        return $output;
    }

    public function addResearchDataStatePropsToPublicationSchema(string $hookName, array $args): bool
    {
        $schema =& $args[0];

        $schema->properties->{'researchDataState'} = (object) [
            'type' => 'string',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];

        $schema->properties->{'researchDataUrl'} = (object) [
            'type' => 'string',
            'apiSummary' => true,
            'validation' => ['nullable', 'url'],
        ];

        $schema->properties->{'researchDataReason'} = (object) [
            'type' => 'string',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];

        return false;
    }

    public function saveResearchDataState(string $hookName, array $args): bool
    {
        $step = $args[0];
        $stepForm = $args[2];

        if (!$this->isValidStepForm($step, $stepForm)) {
            return false;
        }

        $submissionId = $stepForm->execute();
        $submission = Services::get('submission')->get($submissionId);
        $publication = $submission->getCurrentPublication();

        $params = $this->createPublicationParams($stepForm);

        $newPublication = Services::get('publication')->edit($publication, $params, \Application::get()->getRequest());
        $stepForm->submission = Services::get('submission')->get($newPublication->getData('submissionId'));

        return false;
    }

    private function isValidStepForm(int $step, SubmissionSubmitForm &$stepForm): bool
    {
        if ($step !== 1 || !$stepForm->validate()) {
            return false;
        }

        $stepForm->readUserVars(['researchDataState', 'researchDataUrl', 'researchDataReason']);
        $researchDataState = $stepForm->getData('researchDataState');

        if (empty($researchDataState)) {
            $stepForm->addError(
                'researchDataState',
                __('plugins.generic.dataverse.researchDataState.required')
            );
            return false;
        }

        return true;
    }

    private function createPublicationParams(SubmissionSubmitForm $stepForm): array
    {
        $researchDataState = $stepForm->getData('researchDataState');

        $params = [
            'researchDataState' => $researchDataState,
            'researchDataUrl' => null,
            'researchDataReason' => null,
        ];

        if ($researchDataState === RESEARCH_DATA_REPO_AVAILABLE) {
            $params['researchDataUrl'] = $stepForm->getData('researchDataUrl');
        }

        if ($researchDataState === RESEARCH_DATA_PRIVATE) {
            $params['researchDataReason'] = $stepForm->getData('researchDataReason');
        }

        return $params;
    }
}
