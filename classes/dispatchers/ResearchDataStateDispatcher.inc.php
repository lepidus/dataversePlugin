<?php

import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');

define('RESEARCH_DATA_IN_MANUSCRIPT', 'inManuscript');
define('RESEARCH_DATA_REPO_AVAILABLE', 'repoAvailable');
define('RESEARCH_DATA_SUBMISSION_DEPOSIT', 'submissionDeposit');
define('RESEARCH_DATA_ON_DEMAND', 'onDemand');
define('RESEARCH_DATA_PRIVATE', 'private');

class ResearchDataStateDispatcher extends DataverseDispatcher
{
    public function registerHooks(): void
    {
        HookRegistry::register('TemplateManager::display', [$this, 'addResearchDataStateStyles']);
        HookRegistry::register('submissionsubmitstep1form::Constructor', [$this, 'addResearchDataStateValidate']);
        HookRegistry::register('submissionsubmitstep1form::display', [$this, 'addResearchDataStateField']);
        HookRegistry::register('Schema::get::submission', [$this, 'addResearchDataStatePropsToSubmissionSchema']);
        HookRegistry::register('SubmissionHandler::saveSubmit', [$this, 'saveResearchDataState']);
        HookRegistry::register('Template::Workflow::Publication', [$this, 'displayResearchDataState']);
    }

    public function getResearchDataStates(): array
    {
        return [
            RESEARCH_DATA_IN_MANUSCRIPT => __('plugins.generic.dataverse.researchDataState.inManuscript'),
            RESEARCH_DATA_REPO_AVAILABLE => __('plugins.generic.dataverse.researchDataState.repoAvailable'),
            RESEARCH_DATA_SUBMISSION_DEPOSIT => __('plugins.generic.dataverse.researchDataState.submissionDeposit'),
            RESEARCH_DATA_ON_DEMAND => __('plugins.generic.dataverse.researchDataState.onDemand'),
            RESEARCH_DATA_PRIVATE => __('plugins.generic.dataverse.researchDataState.private')
        ];
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
            'required',
            'plugins.generic.dataverse.researchDataState.repoAvailable.url.required'
        ));

        return false;
    }

    public function addResearchDataStateField(string $hookName, array $args): bool
    {
        $request = PKPApplication::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);

        $templateMgr->assign('researchDataStates', $this->getResearchDataStates());

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

    public function addResearchDataStatePropsToSubmissionSchema(string $hookName, array $args): bool
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
            'validation' => ['nullable'],
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
        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        $stepForm = $args[2];

        if ($step != 1 || !$stepForm->validate()) {
            return false;
        }

        $submissionId = $stepForm->execute();
        $submission = $submissionDao->getById($submissionId);

        $stepForm->readUserVars(['researchDataState']);
        $researchDataState = $stepForm->getData('researchDataState');

        if (empty($researchDataState)) {
            $stepForm->addError(
                'researchDataState',
                __('plugins.generic.dataverse.researchDataState.required')
            );
            return false;
        }

        $submission->setData('researchDataState', $researchDataState);

        $researchData = $stepForm->getData('researchDataState');

        if ($researchData == RESEARCH_DATA_REPO_AVAILABLE) {
            $stepForm->readUserVars(['researchDataUrl']);
            $submission->setData('researchDataUrl', $stepForm->getData('researchDataUrl'));
        } else {
            $submission->setData('researchDataUrl', null);
        }

        if ($researchData == RESEARCH_DATA_PRIVATE) {
            $stepForm->readUserVars(['researchDataReason']);
            $submission->setData('researchDataReason', $stepForm->getData('researchDataReason'));
        } else {
            $submission->setData('researchDataReason', null);
        }

        $submissionDao->updateObject($submission);
        $stepForm->submission = $submission;

        return false;
    }
}
