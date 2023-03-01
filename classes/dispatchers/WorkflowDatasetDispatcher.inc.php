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

    private function getPluginFullPath(): string
    {
        $request = Application::get()->getRequest();
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

        $apiClient = new NativeAPIClient($submission->getContextId());
        $dataService = new DataAPIService($apiClient);
        try {
            $dataset = $dataService->getDataset($study->getPersistentId());
            return $templateMgr->fetch($this->plugin->getTemplateResource('datasetTab/datasetData.tpl'));
        } catch (Exception $e) {
            error_log($e->getMessage());
            $templateMgr->assign('errorMessage', $e->getMessage());
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

        $pluginPath = $this->getPluginFullPath();
        $params = ['contexts' => ['backend']];

        $templateMgr->addStyleSheet(
            'datasetTab',
            $pluginPath . '/styles/datasetDataTab.css',
            $params
        );

        $submission = $templateMgr->get_template_vars('submission');
        $study = $this->getSubmissionStudy($submission->getId());

        if (is_null($study)) {
            $this->setupResearchDataDeposit($submission);
        }

        return false;
    }

    private function setupResearchDataDeposit(Submission $submission): void
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $action = $request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'datasets', null, null, ['submissionId' => $submission->getId()]);

        import('plugins.generic.dataverse.classes.creators.SubmissionAdapterCreator');
        $submissionAdapterCreator = new SubmissionAdapterCreator();
        $submissionAdapter = $submissionAdapterCreator->create($submission, $request->getUser());

        import('plugins.generic.dataverse.classes.factories.dataset.SubmissionDatasetFactory');
        $factory = new SubmissionDatasetFactory($submissionAdapter);
        $dataset = $factory->getDataset();

        $this->plugin->import('classes.form.DatasetMetadataForm');
        $datasetMetadataForm = new DatasetMetadataForm($action, 'POST', $dataset);

        HookRegistry::register('Form::config::before', function ($hookName, $form) {
            if ($form->id != FORM_DATASET_METADATA) {
                return;
            }

            $form->addField(new \PKP\components\forms\FieldHTML('noResearchData', [
                'description' => __("plugins.generic.dataverse.researchData.noResearchData"),
                'groupId' => 'default',
            ]), [FIELD_POSITION_BEFORE, 'datasetTitle']);
        });

        $templateMgr = TemplateManager::getManager($request);
        $this->addComponent($templateMgr, $datasetMetadataForm);

        $workflowPublicationFormIds = $templateMgr->getState('publicationFormIds');
        $workflowPublicationFormIds[] = FORM_DATASET_METADATA;

        $templateMgr->setState([
            'publicationFormIds' => $workflowPublicationFormIds
        ]);
    }

    private function addComponent($templateMgr, $component, $args = []): void
    {
        $workflowComponents = $templateMgr->getState('components');
        $workflowComponents[$component->id] = $component->getConfig();

        if (!empty($args)) {
            foreach ($args as $prop => $value) {
                $workflowComponents[$component->id][$prop] = $value;
            }
        }

        $templateMgr->setState([
            'components' => $workflowComponents
        ]);
    }
}
