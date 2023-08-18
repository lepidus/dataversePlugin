<?php

import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');
import('plugins.generic.dataverse.classes.services.DataStatementService');

class DataStatementDispatcher extends DataverseDispatcher
{
    public function registerHooks(): void
    {
        HookRegistry::register('TemplateManager::display', [$this, 'addDataStatementResources']);
        HookRegistry::register('submissionsubmitstep1form::display', [$this, 'addDataStatementField']);
        HookRegistry::register('submissionsubmitstep1form::readuservars', [$this, 'readDataStatementVars']);
        HookRegistry::register('SubmissionHandler::saveSubmit', [$this, 'saveDataStatement']);
        HookRegistry::register('Schema::get::publication', [$this, 'addDataStatementToPublicationSchema']);
        HookRegistry::register('Publication::validate', [$this, 'validateDataStatementProps']);
        HookRegistry::register('Templates::Preprint::Details', [$this, 'viewDataStatement']);
        HookRegistry::register('Templates::Article::Details', [$this, 'viewDataStatement']);
    }

    public function addDataStatementResources(string $hookName, array $args): bool
    {
        $templateMgr = $args[0];
        $template = $args[1];

        if (
            $template === 'frontend/pages/preprint.tpl'
            || $template === 'frontend/pages/article.tpl'
        ) {
            $templateMgr->addStyleSheet(
                'dataStatementList',
                $this->plugin->getPluginFullPath() . '/styles/dataStatementList.css',
                ['contexts' => ['frontend']]
            );

            return false;
        }

        if ($template !== 'submission/form/index.tpl') {
            return false;
        }

        $templateMgr->addStyleSheet(
            'dataStatement',
            $this->plugin->getPluginFullPath() . '/styles/dataStatement.css',
            ['contexts' => ['backend']]
        );

        $templateMgr->setConstants([
            'DATA_STATEMENT_TYPE_IN_MANUSCRIPT',
            'DATA_STATEMENT_TYPE_REPO_AVAILABLE',
            'DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED',
            'DATA_STATEMENT_TYPE_ON_DEMAND',
            'DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE',
        ]);

        return false;
    }

    public function addDataStatementField(string $hookName, array $args): bool
    {
        $request = PKPApplication::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);

        $dataStatementService = new DataStatementService();

        $templateMgr->assign('allDataStatementsTypes', $dataStatementService->getDataStatementTypes());

        $templateMgr->registerFilter("output", array($this, 'dataStatementFilter'));
        return false;
    }

    public function dataStatementFilter(string $output, Smarty_Internal_Template $templateMgr)
    {
        if (preg_match('/<div[^>]+id="pkp_submissionChecklist/', $output, $matches, PREG_OFFSET_CAPTURE)) {
            $match = $matches[0][0];
            $posMatch = $matches[0][1];

            $dataStatementTemplate = $templateMgr->fetch(
                $this->plugin->getTemplateResource('dataStatement.tpl')
            );

            $output = substr_replace($output, $dataStatementTemplate, $posMatch, 0);
            $templateMgr->unregisterFilter('output', array($this, 'dataStatementFilter'));
        }
        return $output;
    }

    public function addDataStatementToPublicationSchema(string $hookName, array $args): bool
    {
        $schema = &$args[0];

        $schema->properties->dataStatementTypes = (object) [
            'type' => 'array',
            'items' => (object) [
                'type' => 'integer',
            ]
        ];

        $schema->properties->dataStatementUrls = (object) [
            'type' => 'array',
            'items' => (object) [
                'type' => 'string',
            ]
        ];

        $schema->properties->dataStatementReason = (object) [
            'type' => 'string',
            'multilingual' => true
        ];

        return false;
    }

    public function readDataStatementVars(string $hookName, array $args): bool
    {
        $vars = &$args[1];

        array_push($vars, 'dataStatementTypes', 'keywords', 'dataStatementReason');

        return false;
    }

    public function saveDataStatement(string $hookname, array $args): bool
    {
        $step = $args[0];
        $stepForm = $args[2];

        if (!$this->isValidStepForm($step, $stepForm)) {
            return false;
        }

        $submissionId = $stepForm->execute();
        $submission = Services::get('submission')->get($submissionId);
        $publication = $submission->getCurrentPublication();

        $params = $this->createDataStatementParams($stepForm);

        $newPublication = Services::get('publication')->edit($publication, $params, \Application::get()->getRequest());
        $stepForm->submission = Services::get('submission')->get($newPublication->getData('submissionId'));

        return false;
    }

    private function isValidStepForm(int $step, SubmissionSubmitForm &$stepForm): bool
    {
        if ($step !== 1 || !$stepForm->validate()) {
            return false;
        }

        if (empty($stepForm->getData('dataStatementTypes'))) {
            $stepForm->addError(
                'dataStatementTypes',
                __('plugins.generic.dataverse.dataStatement.required')
            );
            $stepForm->addErrorField('dataStatementTypes');
            return false;
        }

        if (in_array(DATA_STATEMENT_TYPE_REPO_AVAILABLE, $stepForm->getData('dataStatementTypes'))) {
            if(empty($stepForm->getData('keywords')['dataStatementUrls'])) {
                $stepForm->addError(
                    'dataStatementUrls',
                    __('plugins.generic.dataverse.dataStatement.repoAvailable.urls.required')
                );
                $stepForm->addErrorField('dataStatementUrls');
                return false;
            } else {
                foreach($stepForm->getData('keywords')['dataStatementUrls'] as $dataStatementUrl) {
                    if(!$this->inputIsURL($dataStatementUrl)) {
                        $stepForm->addError(
                            'dataStatementUrls',
                            __('plugins.generic.dataverse.dataStatement.repoAvailable.urls.urlFormat')
                        );
                        $stepForm->addErrorField('dataStatementUrls');
                        $stepForm->setData('keywords', null);
                        return false;
                    }
                }
            }
        }

        return true;
    }

    private function createDataStatementParams(SubmissionSubmitForm $stepForm): array
    {
        $dataStatementTypes = $stepForm->getData('dataStatementTypes');
        $dataStatementUrls = null;
        $dataStatementReason = null;

        if (in_array(DATA_STATEMENT_TYPE_REPO_AVAILABLE, $dataStatementTypes)) {
            $dataStatementUrls = $stepForm->getData('keywords')['dataStatementUrls'];
        }

        if (in_array(DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE, $dataStatementTypes)) {
            $dataStatementReason = $stepForm->getData('dataStatementReason');
        }

        return [
            'dataStatementTypes' => $dataStatementTypes,
            'dataStatementUrls' => $dataStatementUrls,
            'dataStatementReason' => $dataStatementReason
        ];
    }

    private function inputIsURL(string $input): bool
    {
        $urlPattern = '/^(https?:\/\/)?[a-z0-9\-]+(\.[a-z0-9\-]+)+([\/?#].*)?$/i';
        return preg_match($urlPattern, $input) === 1;
    }

    public function validateDataStatementProps(string $hookName, array $args): bool
    {
        $errors = &$args[0];
        $props = $args[2];

        if (!isset($props['dataStatementTypes'])) {
            return false;
        }

        if (empty($errors)) {
            $errors = [];
        }

        if (empty($props['dataStatementTypes'])) {
            $errors['dataStatementTypes'] = [__('plugins.generic.dataverse.dataStatement.required')];
        }

        if (
            in_array(DATA_STATEMENT_TYPE_REPO_AVAILABLE, $props['dataStatementTypes'])
            && empty($props['dataStatementUrls'])
        ) {
            $errors['dataStatementUrls'] = [__('plugins.generic.dataverse.dataStatement.repoAvailable.urls.required')];
        }

        return false;
    }

    public function viewDataStatement(string $hookName, array $params): bool
    {
        $templateMgr = & $params[1];
        $output = & $params[2];

        $dataStatementService = new DataStatementService();
        $allDataStatementTypes = $dataStatementService->getDataStatementTypes();
        unset($allDataStatementTypes[DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED]);

        $templateMgr->assign('allDataStatementTypes', $allDataStatementTypes);

        $output .= $templateMgr->fetch($this->plugin->getTemplateResource('listDataStatement.tpl'));

        return false;
    }
}
