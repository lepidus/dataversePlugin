<?php

import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');
import('plugins.generic.dataverse.classes.services.DataStatementService');

class DataStatementTabDispatcher extends DataverseDispatcher
{
    protected function registerHooks(): void
    {
        HookRegistry::register('TemplateManager::setupBackendPage', [$this, 'addDataStatementConstants']);
        HookRegistry::register('TemplateManager::display', [$this, 'addDataStatementTabResources']);
        HookRegistry::register('Template::Workflow::Publication', [$this, 'addDataStatementTab']);
    }

    public function addDataStatementConstants(string $hookName): void
    {
        import('plugins.generic.dataverse.classes.services.DataStatementService');
        $request = \Application::get()->getRequest();
        $templateMgr = \TemplateManager::getManager($request);
        $templateMgr->setConstants([
            'DATA_STATEMENT_TYPE_IN_MANUSCRIPT',
            'DATA_STATEMENT_TYPE_REPO_AVAILABLE',
            'DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED',
            'DATA_STATEMENT_TYPE_ON_DEMAND',
            'DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE',
        ]);

        return;
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

        $templateMgr->addStyleSheet(
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
        );

        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $submission = $templateMgr->get_template_vars('submission');
        $latestPublication = $submission->getLatestPublication();

        $publicationEndpoint = 'submissions/' . $submission->getId() . '/publications/' . $latestPublication->getId();
        $apiUrl = $request->getDispatcher()->url(
            $request,
            ROUTE_API,
            $context->getPath(),
            $publicationEndpoint
        );

        $supportedFormLocales = $context->getSupportedFormLocales();
        $localeNames = AppLocale::getAllLocales();
        $locales = array_map(function ($localeKey) use ($localeNames) {
            return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
        }, $supportedFormLocales);

        import('plugins.generic.dataverse.classes.components.forms.DataStatementForm');
        $dataStatementForm = new DataStatementForm($apiUrl, $locales, $latestPublication);

        $components = $templateMgr->getState('components');
        $publicationFormIds = $templateMgr->getState('publicationFormIds');
        $templateMgr->setConstants(['FORM_DATA_STATEMENT']);

        $components[FORM_DATA_STATEMENT] = $dataStatementForm->getConfig();
        $publicationFormIds[] = FORM_DATA_STATEMENT;

        $templateMgr->setState([
            'components' => $components,
            'publicationFormIds' => $publicationFormIds
        ]);

        return false;
    }

    public function addDataStatementTab(string $hookName, array $params): bool
    {
        $templateMgr =& $params[1];
        $output =& $params[2];

        $output .= $templateMgr->fetch($this->plugin->getTemplateResource('dataStatementTab.tpl'));

        return false;
    }
}
