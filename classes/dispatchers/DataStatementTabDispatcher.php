<?php

namespace APP\plugins\generic\dataverse\classes\dispatchers;

use PKP\plugins\Hook;
use APP\core\Application;
use APP\template\TemplateManager;
use APP\plugins\generic\dataverse\classes\dispatchers\DataverseDispatcher;
use APP\plugins\generic\dataverse\classes\services\DataStatementService;
use APP\plugins\generic\dataverse\classes\components\forms\DataStatementForm;

class DataStatementTabDispatcher extends DataverseDispatcher
{
    protected function registerHooks(): void
    {
        Hook::add('TemplateManager::display', [$this, 'addDataStatementTabResources']);
        Hook::add('Template::Workflow::Publication', [$this, 'addDataStatementTab']);
    }

    public function addDataStatementTabResources(string $hookName, array $params): bool
    {
        $templateMgr = $params[0];
        $template = $params[1];

        if (
            $template != 'workflow/workflow.tpl'
            && $template != 'authorDashboard/authorDashboard.tpl'
        ) {
            return false;
        }

        /*$templateMgr->addStyleSheet(
            'dataStatementTab',
            $this->plugin->getPluginFullPath() . '/styles/dataStatementTab.css',
            ['contexts' => ['backend']]
        );

        $templateMgr->addJavaScript(
            'dataStatementForm',
            $this->plugin->getPluginFullPath() . '/js/ui/components/DataStatementForm.js',
            [
                'priority' => STYLE_SEQUENCE_LAST,
                'contexts' => ['backend']
            ]
        );*/

        $templateMgr->addJavaScript(
            'field-controlled-vocab-url',
            $this->plugin->getPluginFullPath() . '/js/ui/components/FieldControlledVocabUrl.js',
            [
                'priority' => TemplateManager::STYLE_SEQUENCE_LAST,
                'contexts' => ['backend']
            ]
        );

        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $submission = $templateMgr->getTemplateVars('submission');
        $publication = $submission->getLatestPublication();
        $publicationEndpoint = 'submissions/' . $submission->getId() . '/publications/' . $publication->getId();
        $saveFormUrl = $request->getDispatcher()->url($request, Application::ROUTE_API, $context->getPath(), $publicationEndpoint);
        $dataStatementForm = new DataStatementForm($saveFormUrl, $publication, 'workflow');

        $components = $templateMgr->getState('components');
        $components[$dataStatementForm->id] = $dataStatementForm->getConfig();

        $templateMgr->setState(['components' => $components]);

        return false;
    }

    public function addDataStatementTab(string $hookName, array $params): bool
    {
        $templateMgr = &$params[1];
        $output = &$params[2];

        $output .= $templateMgr->fetch($this->plugin->getTemplateResource('dataStatementTab.tpl'));

        return false;
    }
}
