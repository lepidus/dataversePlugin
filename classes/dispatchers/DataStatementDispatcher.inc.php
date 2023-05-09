<?php

import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');
import('plugins.generic.dataverse.classes.services.DataStatementService');

class DataStatementDispatcher extends DataverseDispatcher
{
    public function registerHooks(): void
    {
        HookRegistry::register('TemplateManager::display', [$this, 'addDataStatementFieldStyles']);
        HookRegistry::register('submissionsubmitstep1form::Constructor', [$this, 'addDataStatementValidation']);
        HookRegistry::register('submissionsubmitstep1form::display', [$this, 'addDataStatementField']);
    }

    public function addDataStatementFieldStyles(string $hookName, array $args): bool
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

        return false;
    }

    public function addDataStatementValidation(string $hookName, array $args): bool
    {
        $form =& $args[0];

        $form->addCheck(new FormValidatorUrl(
            $form,
            'dataStatementUrl',
            'optional',
            'plugins.generic.dataverse.dataStatement.repoAvailable.url.required'
        ));

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
}
