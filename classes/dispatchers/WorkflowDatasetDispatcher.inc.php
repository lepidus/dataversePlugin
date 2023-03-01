<?php

import('plugins.generic.dataverse.classes.dataverseAPI.clients.NativeAPIClient');
import('plugins.generic.dataverse.classes.dataverseAPI.services.DataAPIService');
import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');
import('lib.pkp.classes.submission.SubmissionFile');

class WorkflowDatasetDispatcher extends DataverseDispatcher
{
    protected function registerHooks(): void
    {
        HookRegistry::register('Template::Workflow::Publication', array($this, 'addResearchDataTab'));
        HookRegistry::register('TemplateManager::display', array($this, 'loadResourcesToWorkflow'));
    }

    private function getSubmissionStudy(int $submissionId): ?DataverseStudy
    {
        $dataverseStudyDao =& DAORegistry::getDAO('DataverseStudyDAO');
        $study = $dataverseStudyDao->getStudyBySubmissionId($submissionId);
        return $study;
    }

    private function getPluginFullPath($request): string
    {
        return $request->getBaseUrl() . DIRECTORY_SEPARATOR . $this->plugin->getPluginPath();
    }

    public function addResearchDataTab(string $hookName, array $params): bool
    {
        $templateMgr =& $params[1];
        $output =& $params[2];

        $content = $this->getDatasetTabContent($templateMgr);

        $output .= sprintf(
            '<tab id="datasetTab" label="%s">%s</tab>',
            __("plugins.generic.dataverse.researchData"),
            $content
        );

        return false;
    }

    private function getDatasetTabContent(Smarty_Internal_Template $templateMgr): string
    {
        $submission = $templateMgr->get_template_vars('submission');
        $study = $this->getSubmissionStudy($submission->getId());

        if (is_null($study)) {
            return $templateMgr->fetch($this->plugin->getTemplateResource('datasetTab/noResearchData.tpl'));
        }

        try {
            $apiClient = new NativeAPIClient($submission->getContextId());
            $dataService = new DataAPIService($apiClient);
            $dataset = $dataService->getDataset($study->getPersistentId());
            return $templateMgr->fetch($this->plugin->getTemplateResource('datasetTab/datasetData.tpl'));
        } catch (Exception $e) {
            error_log($e->getMessage());
            $templateMgr->assign(
                'errorMessage',
                __(
                    'plugins.generic.dataverse.notification.researchDataNotFound',
                    ['persistentId' => $study->getPersistentId()]
                )
            );
            return $templateMgr->fetch($this->plugin->getTemplateResource('datasetTab/researchDataNotFound.tpl'));
        }
    }

    public function loadResourcesToWorkflow(string $hookName, array $params): bool
    {
        $templateMgr = $params[0];
        $template = $params[1];

        if (
            $template != 'workflow/workflow.tpl'
            && $template != 'authorDashboard/authorDashboard.tpl'
        ) {
            return false;
        }

        $request = Application::get()->getRequest();

        $pluginPath = $this->getPluginFullPath($request);
        $params = [
            'contexts' => ['backend']
        ];

        $templateMgr->addStyleSheet(
            'datasetTab',
            $pluginPath . '/styles/datasetDataTab.css',
            $params
        );

        return false;
    }
}
