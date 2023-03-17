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
        HookRegistry::register('Form::config::before', array($this, 'addDatasetPublishNotice'));
    }

    private function getSubmissionStudy(int $submissionId): ?DataverseStudy
    {
        $dataverseStudyDao =& DAORegistry::getDAO('DataverseStudyDAO');
        $study = $dataverseStudyDao->getStudyBySubmissionId($submissionId);
        return $study;
    }

    public function addResearchDataTab(string $hookName, array $params): bool
    {
        $templateMgr =& $params[1];
        $output =& $params[2];

        $submission = $templateMgr->get_template_vars('submission');

        $content = $this->getDatasetTabContent($submission);

        $output .= sprintf(
            '<tab id="datasetTab" label="%s">%s</tab>',
            __("plugins.generic.dataverse.researchData"),
            $templateMgr->fetch($content)
        );

        return false;
    }

    private function getDatasetTabContent(Submission $submission): string
    {
        $study = $this->getSubmissionStudy($submission->getId());

        if (is_null($study)) {
            return $this->plugin->getTemplateResource('datasetTab/noResearchData.tpl');
        }

        $apiClient = new NativeAPIClient($submission->getContextId());
        $dataService = new DataAPIService($apiClient);
        try {
            $dataset = $dataService->getDataset($study->getPersistentId());
            return $this->plugin->getTemplateResource('datasetTab/datasetData.tpl');
        } catch (Exception $e) {
            error_log($e->getMessage());
            $templateMgr->assign('errorMessage', $e->getMessage());
            return $this->plugin->getTemplateResource('datasetTab/researchDataError.tpl');
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

        $templateMgr->addStyleSheet(
            'datasetTab',
            $this->plugin->getPluginFullPath() . '/styles/datasetDataTab.css',
            ['contexts' => ['backend']]
        );

        $templateMgr->addJavaScript(
            'dataverseWorkflowPage',
            $this->plugin->getPluginFullPath() . '/js/DataverseWorkflowPage.js',
            [
                'priority' => STYLE_SEQUENCE_LAST,
                'contexts' => ['backend']
            ]
        );

        $templateMgr->assign([
            'pageComponent' => 'DataverseWorkflowPage',
        ]);

        $submission = $templateMgr->get_template_vars('submission');
        $study = $this->getSubmissionStudy($submission->getId());

        if (is_null($study)) {
            $this->setupResearchDataDeposit($submission);
            return false;
        }

        $this->setupResearchDataUpdate($study);

        return false;
    }

    private function setupResearchDataDeposit(Submission $submission): void
    {
        $request = Application::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);
        $context = $request->getContext();

        $metadataFormAction = $request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'datasets', null, null, ['submissionId' => $submission->getId()]);

        $templateMgr->setState([
            'datasetApiUrl' => $metadataFormAction
        ]);

        import('plugins.generic.dataverse.classes.factories.dataset.SubmissionDatasetFactory');
        $factory = new SubmissionDatasetFactory($submission);
        $dataset = $factory->getDataset();

        $fileListApiUrl = 'teste';
        $items = $items = array_map(function (DatasetFile $datasetFile) {
            return $datasetFile->getVars();
        }, $dataset->getFiles());

        // $fileFormAction = $request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'datasets/' . $study->getId() . '/file');
        $fileFormAction = 'teste';

        $this->initDatasetMetadataForm($templateMgr, $metadataFormAction, 'POST', $dataset);
        $this->initDatasetFilesList($templateMgr, $fileListApiUrl, $items);
        $this->initDatasetFileForm($templateMgr, $fileFormAction);
    }

    private function setupResearchDataUpdate(DataverseStudy $study): void
    {
        $request = Application::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);
        $context = $request->getContext();

        $templateMgr->setState([
            'datasetApiUrl' => $action
        ]);

        $templateMgr->addJavaScript(
            'dataverseHelper',
            $this->plugin->getPluginFullPath() . '/js/DataverseHelper.js',
            ['contexts' => ['backend']]
        );

        $templateMgr->addJavaScript(
            'dataverseScripts',
            $this->plugin->getPluginFullPath() . '/js/init.js',
            ['contexts' => ['backend']]
        );

        $this->addJavaScriptVariables($request, $templateMgr, $study);

        try {
            $client = new NativeAPIClient($context->getId());
            $service = new DataAPIService($client);
            $dataset = $service->getDataset($study->getPersistentId());
            $datasetFiles = $service->getDatasetFiles($study->getPersistentId());

            $metadataFormAction = $request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'datasets/' . $study->getId());
            $fileListApiUrl = $request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'datasets/' . $study->getId() . '/file', null, null, ['fileId' => '__id__']);
            $fileFormAction = $dispatcher->url($request, ROUTE_API, $context->getPath(), 'datasets/' . $study->getId() . '/file');
            $items = array_map(function (DatasetFile $datasetFile) {
                return $datasetFile->getVars();
            }, $datasetFiles);

            $this->initDatasetMetadataForm($templateMgr, $metadataFormAction, 'PUT', $dataset);
            $this->initDatasetFilesList($templateMgr, $fileListApiUrl, $items);
            $this->initDatasetFileForm($templateMgr, $fileFormAction);
        } catch (Exception $e) {
            error_log($e->getMessage());
        }
    }

    private function initDatasetMetadataForm(PKPTemplateManager $templateMgr, string $action, string $method, Dataset $dataset): void
    {
        $this->plugin->import('classes.form.DatasetMetadataForm');
        $datasetMetadataForm = new DatasetMetadataForm($action, $method, $dataset);

        $this->addComponent($templateMgr, $datasetMetadataForm);

        $workflowPublicationFormIds = $templateMgr->getState('publicationFormIds');
        $workflowPublicationFormIds[] = FORM_DATASET_METADATA;

        $templateMgr->setState([
            'publicationFormIds' => $workflowPublicationFormIds
        ]);
    }

    private function initDatasetFilesList($templateMgr, $apiUrl, $items): void
    {
        import('plugins.generic.dataverse.classes.listPanel.DatasetFilesListPanel');
        $datasetFilesListPanel = new DatasetFilesListPanel(
            'datasetFiles',
            __('plugins.generic.dataverse.researchData.files'),
            [
                'addFileLabel' => __('plugins.generic.dataverse.addResearchData'),
                'apiUrl' => $apiUrl,
                'items' => $items,
                'modalTitle' => __('plugins.generic.dataverse.modal.addFile.title')
            ]
        );

        $this->addComponent($templateMgr, $datasetFilesListPanel);

        $templateMgr->setState([
            'deleteDatasetFileLabel' => __('plugins.generic.dataverse.modal.deleteDatasetFile'),
            'deleteDatasetLabel' => __('plugins.generic.dataverse.researchData.delete'),
            'confirmDeleteMessage' => __('plugins.generic.dataverse.modal.confirmDelete'),
            'confirmDeleteDatasetMessage' => __('plugins.generic.dataverse.modal.confirmDatasetDelete'),
        ]);
    }

    public function initDatasetFileForm($templateMgr, $apiUrl): void
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        import('plugins.generic.dataverse.classes.form.DraftDatasetFileForm');
        $draftDatasetFileForm = new DraftDatasetFileForm($apiUrl, $context);

        $this->addComponent(
            $templateMgr,
            $draftDatasetFileForm,
            [
                'errors' => [
                    'termsOfUse' => [
                        __('plugins.generic.dataverse.termsOfUse.error')
                    ]
                ]
            ]
        );
    }

    public function addJavaScriptVariables($request, $templateManager, $study): void
    {
        $dispatcher = $request->getDispatcher();
        $context = $request->getContext();

        $credentials = DAORegistry::getDAO('DataverseCredentialsDAO')->get($context->getId());
        $dataverseUrl = $credentials->getDataverseUrl();
        $params = ['dataverseUrl' => $dataverseUrl];

        import('plugins.generic.dataverse.classes.DataverseNotificationManager');
        $dataverseNotificationMgr = new DataverseNotificationManager();
        $errorMessage = $dataverseNotificationMgr->getNotificationMessage(DATAVERSE_PLUGIN_HTTP_STATUS_BAD_REQUEST, $params);

        $apiUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), 'datasets/' . $study->getId());

        $data = [
            'datasetApiUrl' => $apiUrl,
            "errorMessage" => $errorMessage,
        ];

        $templateManager->addJavaScript('dataverse', 'appDataverse = ' . json_encode($data) . ';', [
            'inline' => true,
            'contexts' => ['backend', 'frontend']
        ]);
    }

    private function addComponent(PKPTemplateManager $templateMgr, $component, $args = []): void
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

    public function addDatasetPublishNotice(string $hookName, \PKP\components\forms\FormComponent $form): void
    {
        if ($form->id !== 'publish' || !empty($form->errors)) {
            return;
        }

        $study = DAORegistry::getDAO('DataverseStudyDAO')->getStudyBySubmissionId($form->publication->getData('submissionId'));

        if (empty($study)) {
            return;
        }

        $contentId = $form->submissionContext->getId();
        $client = new NativeAPIClient($contentId);
        $service = new DataAPIService($client);

        $params = [
            'persistentUri' => $study->getPersistentUri(),
            'serverName' => $service->getDataverseServerName(),
            'serverUrl' => $client->getCredentials()->getDataverseServerUrl(),
        ];

        $form->addField(new \PKP\components\forms\FieldHTML('researchData', [
            'description' => __("plugin.generic.dataverse.notification.submission.researchData", $params),
            'groupId' => 'default',
        ]));
    }
}
