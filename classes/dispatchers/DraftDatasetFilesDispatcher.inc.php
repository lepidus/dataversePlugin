<?php

import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');

class DraftDatasetFilesDispatcher extends DataverseDispatcher
{
    public function __construct(Plugin $plugin)
    {
        HookRegistry::register('submissionsubmitstep2form::display', array($this, 'addDraftDatasetFilesContainer'));
        HookRegistry::register('submissionsubmitstep2form::validate', array($this, 'validateDraftDatasetFiles'));
        HookRegistry::register('TemplateManager::display', array($this, 'loadDraftDatasetFilePageComponent'));

        parent::__construct($plugin);
    }

    public function loadDraftDatasetFilePageComponent(string $hookName, array $params): bool
    {
        $templateMgr = &$params[0];
        $request = Application::get()->getRequest();

        $templateMgr->addJavaScript(
            'draftDatasetFilePage',
            $this->plugin->getPluginFullPath() . '/js/DraftDatasetFilesPage.js',
            [
                'contexts' => ['backend'],
                'priority' => STYLE_SEQUENCE_LAST,
            ]
        );

        $templateMgr->addStyleSheet(
            'draftDatasetFileUpload',
            $this->plugin->getPluginFullPath() . '/styles/draftDatasetFileUpload.css',
            [
                'contexts' => ['backend']
            ]
        );

        return false;
    }

    public function addDraftDatasetFilesContainer(string $hookName, array $params): bool
    {
        $request = PKPApplication::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);

        $form = $params[0];
        $form->readUserVars(array('submissionId'));
        $submissionId = $form->getData('submissionId');

        $templateMgr->assign('submissionId', $submissionId);

        $templateMgr->registerFilter("output", array($this, 'draftDatasetFilesContainerFilter'));

        return false;
    }

    public function draftDatasetFilesContainerFilter(string $output, Smarty_Internal_Template $templateMgr): string
    {
        if (
            preg_match('/<div[^>]+class="section formButtons form_buttons[^>]*"[^>]*>/', $output, $matches, PREG_OFFSET_CAPTURE)
            && $templateMgr->template_resource == 'submission/form/step2.tpl'
        ) {
            $datasetFilesContainer = $this->getDraftDatasetFilesContainer();
            $newOutput = $templateMgr->fetch('string:' . $datasetFilesContainer);
            $newOutput .= $output;
            $output = $newOutput;
            $templateMgr->unregisterFilter('output', array($this, 'datasetFileFormFilter'));
        }

        return $output;
    }

    private function getDraftDatasetFilesContainer(): string
    {
        return '
            {capture assign=draftDatasetFileFormUrl}
                {url 
                    router=$smarty.const.ROUTE_COMPONENT 
                    component="plugins.generic.dataverse.handlers.DataverseHandler" 
                    op="draftDatasetFiles"
                    submissionId=$submissionId
                    escape=false
                }
            {/capture}
            {load_url_in_div id=""|uniqid|escape url=$draftDatasetFileFormUrl}
        ';
    }

    public function validateDraftDatasetFiles(string $hookName, array $params): bool
    {
        $form =& $params[0];
        $submission = $form->submission;

        $galleys = $submission->getGalleys();
        $galleyFiles = array_map(function ($galley) {
            return Services::get('submissionFile')->get($galley->getFileId());
        }, $galleys);

        return false;
    }
}
