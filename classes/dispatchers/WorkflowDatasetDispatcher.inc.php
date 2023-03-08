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
            return $templateMgr->fetch($this->plugin->getTemplateResource('datasetTab/researchDataError.tpl'));
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
            $this->getPluginFullPath() . '/styles/datasetDataTab.css',
            ['contexts' => ['backend']]
        );

        $templateMgr->addJavaScript(
            'workflowDataset',
            $this->getPluginFullPath() . '/js/WorkflowDataset.js',
            ['contexts' => ['backend']]
        );

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

        $action = $request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'datasets', null, null, ['submissionId' => $submission->getId()]);

        $templateMgr->setState([
            'datasetApiUrl' => $action
        ]);

        $templateMgr->assign('requestArgs', [
            'submissionId' => $submission->getId(),
            'publicationId' => $submission->getCurrentPublication()->getId(),
        ]);

        import('plugins.generic.dataverse.classes.factories.dataset.SubmissionDatasetFactory');
        $factory = new SubmissionDatasetFactory($submission);
        $dataset = $factory->getDataset();

        $this->initDatasetMetadataForm($templateMgr, $action, 'POST', $dataset);
    }

    private function setupResearchDataUpdate(DataverseStudy $study): void
    {
        $request = Application::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);
        $context = $request->getContext();

        $action = $request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'datasets/' . $study->getId());

        $templateMgr->setState([
            'datasetApiUrl' => $action
        ]);

        $templateMgr->addJavaScript(
            'dataverseHelper',
            $this->getPluginFullPath() . '/js/DataverseHelper.js',
            ['contexts' => ['backend']]
        );

        $templateMgr->addJavaScript(
            'dataverseScripts',
            $this->getPluginFullPath() . '/js/init.js',
            ['contexts' => ['backend']]
        );

        $this->addJavaScriptVariables($request, $templateMgr, $study);

        try {
            $client = new NativeAPIClient($context->getId());
            $service = new DataAPIService($client);
            $dataset = $service->getDataset($study->getPersistentId());

            $this->initDatasetMetadataForm($templateMgr, $action, 'PUT', $dataset);
            $this->initDatasetFilesList($request, $templateMgr, $study);
            $this->initDatasetFileForm($request, $templateMgr, $study);
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

    private function initDatasetFilesList($request, $templateMgr, $study): void
    {
        $context = $request->getContext();

        $client = new NativeAPIClient($context->getId());
        $service = new DataAPIService($client);

        try {
            $datasetFiles = $service->getDatasetFiles($study->getPersistentId());

            $apiUrl = $request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'datasets/' . $study->getId() . '/file', null, null, ['fileId' => '__id__']);

            import('plugins.generic.dataverse.classes.listPanel.DatasetFilesListPanel');
            $datasetFilesListPanel = new DatasetFilesListPanel(
                'datasetFiles',
                __('plugins.generic.dataverse.researchData.files'),
                [
                    'apiUrl' => $apiUrl,
                    'items' => array_map(function (DatasetFile $datasetFile) {
                        return $datasetFile->getVars();
                    }, $datasetFiles)
                ]
            );

            $this->addComponent($templateMgr, $datasetFilesListPanel);

            $templateMgr->setState([
                'deleteDatasetFileLabel' => __('plugins.generic.dataverse.modal.deleteDatasetFile'),
                'deleteDatasetLabel' => __('plugins.generic.dataverse.researchData.delete'),
                'confirmDeleteMessage' => __('plugins.generic.dataverse.modal.confirmDelete'),
                'confirmDeleteDatasetMessage' => __('plugins.generic.dataverse.modal.confirmDatasetDelete'),
            ]);
        } catch (Exception $e) {
            error_log($e->getMessage());
        }
    }

    public function initDatasetFileForm($request, $templateMgr, $study): void
    {
        $dispatcher = $request->getDispatcher();
        $context = $request->getContext();
        $locale = AppLocale::getLocale();

        $temporaryFileApiUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), 'temporaryFiles');
        $apiUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), 'datasets/' . $study->getId() . '/file');

        $supportedFormLocales = $context->getSupportedFormLocales();
        $localeNames = AppLocale::getAllLocales();
        $locales = array_map(function ($localeKey) use ($localeNames) {
            return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
        }, $supportedFormLocales);

        import('plugins.generic.dataverse.classes.form.DraftDatasetFileForm');
        $draftDatasetFileForm = new DraftDatasetFileForm($apiUrl, $context, $locales, $temporaryFileApiUrl);

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
