<?php

import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');
import('plugins.generic.dataverse.classes.services.DataStatementService');
import('plugins.generic.dataverse.classes.dataStatement.DataStatement');

class DataStatementDispatcher extends DataverseDispatcher
{
    public function registerHooks(): void
    {
        HookRegistry::register('TemplateManager::display', [$this, 'addDataStatementFieldResource']);
        HookRegistry::register('submissionsubmitstep1form::display', [$this, 'addDataStatementField']);
        HookRegistry::register('SubmissionHandler::saveSubmit', [$this, 'saveDataStatement']);
        HookRegistry::register('Schema::get::publication', [$this, 'addDataStatementToPublicationSchema']);
        HookRegistry::register('Schema::get::dataStatement', array($this, 'loadDataStatementSchema'));
    }

    public function addDataStatementFieldResource(string $hookName, array $args): bool
    {
        $templateMgr = $args[0];
        $template = $args[1];

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

        $templateMgr->assign('dataStatementTypes', $dataStatementService->getDataStatementTypes());

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
        $schema =& $args[0];

        $schema->properties->{'dataStatements'} = (object) [
            'type' => 'array',
            'apiSummary' => true,
            'validation' => ['nullable'],
            "items" => (object) [
                "\$ref" => "#/definitions/DataStatement",
            ]
        ];

        return false;
    }

    public function loadDataStatementSchema(string $hookname, array $params): bool
    {
        $schema = &$params[0];
        $dataStatementSchema = BASE_SYS_DIR . '/plugins/generic/dataverse/schemas/dataStatement.json';

        if (file_exists($dataStatementSchema)) {
            $schema = json_decode(file_get_contents($dataStatementSchema));
            if (!$schema) {
                fatalError(printf(
                    'Schema failed to decode. This usually means it is invalid JSON. Requested: %s. Last JSON error: %s',
                    $dataStatementSchema,
                    json_last_error()
                ));
            }
        }

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

        return true;
    }

    private function createDataStatementParams(SubmissionSubmitForm $stepForm): array
    {
        $stepForm->readUserVars(['dataStatement']);

        $dataStatements = array_map(function ($dataStatementTypes) {
            $dataStatement = new DataStatement();
            $dataStatement->setType($dataStatementTypes);

            if ($dataStatementTypes === DATA_STATEMENT_TYPE_REPO_AVAILABLE) {
                $stepForm->readUserVars(['dataStatementUrls']);
                $dataStatement->setUrls($stepForm->getData('dataStatementUrls'));
            }

            if ($dataStatementTypes === DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE) {
                $stepForm->readUserVars(['dataStatementReason']);
                $dataStatement->setReason($stepForm->getData('dataStatementReason'));
            }
        }, $stepForm->getData('dataStatement'));

        return $dataStatements;
    }
}
