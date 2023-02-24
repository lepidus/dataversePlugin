<?php

import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');
import('plugins.generic.dataverse.classes.APACitation');
import('lib.pkp.classes.submission.SubmissionFile');
import('plugins.generic.dataverse.classes.study.DataverseStudyDAO');
import('plugins.generic.dataverse.classes.dataverseAPI.clients.NativeAPIClient');
import('plugins.generic.dataverse.classes.dataverseAPI.services.DataAPIService');
import('plugins.generic.dataverse.classes.factories.DataverseServerFactory');

class WorkflowDatasetDispatcher extends DataverseDispatcher
{
    protected function registerHooks(): void
    {
        HookRegistry::register('Template::Workflow::Publication', array($this, 'addResearchDataTab'));
        HookRegistry::register('TemplateManager::display', array($this, 'loadResourcesToWorkflow'));
    }

    private function getSubmissionStudy($submission): ?DataverseStudy
    {
        $dataverseStudyDao =& DAORegistry::getDAO('DataverseStudyDAO');
        $study = $dataverseStudyDao->getStudyBySubmissionId($submission->getId());
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

        $submission = $templateMgr->get_template_vars('submission');
        $study = $this->getSubmissionStudy($submission);

        $content = isset($study) ?
            $templateMgr->fetch($this->plugin->getTemplateResource('datasetData.tpl')) :
            $templateMgr->fetch($this->plugin->getTemplateResource('noResearchData.tpl'));

        $output .= sprintf(
            '<tab id="datasetTab" label="%s">%s</tab>',
            __("plugins.generic.dataverse.researchData"),
            $content
        );

        return false;
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
        $templateMgr->addJavaScript(
            'dataverseHelper',
            $pluginPath . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'dataverseHelper.js',
            $params
        );

        $submission = $templateMgr->get_template_vars('submission');
        $study = $this->getSubmissionStudy($submission);

        $context = $request->getContext();
        $dispatcher = $request->getDispatcher();

        if (empty($study)) {
            $action = $dispatcher->url($request, ROUTE_API, $context->getPath(), 'datasets', null, null, ['submissionId' => $submission->getId()]);
            $method = 'POST';

            import('plugins.generic.dataverse.classes.creators.SubmissionAdapterCreator');
            $submissionAdapterCreator = new SubmissionAdapterCreator();
            $submissionAdapter = $submissionAdapterCreator->create($submission, $request->getUser());

            import('plugins.generic.dataverse.classes.factories.dataset.SubmissionDatasetFactory');
            $factory = new SubmissionDatasetFactory($submission);
            $dataset = $factory->getDataset();
        } else {
            $action = $dispatcher->url($request, ROUTE_API, $context->getPath(), 'datasets/' . $study->getId());
            $method = 'PUT';

            try {
                import('plugins.generic.dataverse.classes.factories.DataverseServerFactory');
                $serverFactory = new DataverseServerFactory();
                $server = $serverFactory->createDataverseServer($context->getId());

                import('plugins.generic.dataverse.classes.dataverseAPI.clients.NativeAPIClient');
                $client = new NativeAPIClient($server);

                import('plugins.generic.dataverse.classes.dataverseAPI.services.DataAPIService');
                $service = new DataAPIService($client);
                $dataset = $service->getDataset($study->getPersistentId());
            } catch (Exception $e) {
                error_log($e->getMessage());
            }
        }

        $this->setupDatasetMetadataForm($request, $templateMgr, $action, $method, $dataset);

        // if (!empty($study)) {
        //     $this->loadJavaScript($pluginPath, $templateMgr);
        //     $this->addJavaScriptVariables($request, $templateMgr, $study);

        //     // $this->setupDatasetFilesList($request, $templateMgr, $study);
        //     // $this->setupDatasetFileForm($request, $templateMgr, $study);
        // }

        return false;
    }

    public function loadJavaScript($pluginPath, $templateManager)
    {
        $templateManager->addJavaScript(
            'dataverseScripts',
            $pluginPath . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'init.js',
            [
                'contexts' => ['backend']
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

    private function setupDatasetMetadataForm(PKPRequest $request, TemplateManager $templateMgr, string $action, string $method, Dataset $dataset): void
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

    private function setupDatasetFilesList($request, $templateMgr, $study): void
    {
        $context = $request->getContext();
        $dispatcher = $request->getDispatcher();

        $datasetFilesResponse = $this->getDataverseService()->getDatasetFiles($study);
        $datasetFiles = array();

        foreach ($datasetFilesResponse->data as $data) {
            $datasetFiles[] = ["id" => $data->dataFile->id, "title" => $data->label];
        }

        $apiUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), 'datasets/' . $study->getId() . '/file', null, null, ['fileId' => '__id__']);

        import('plugins.generic.dataverse.classes.listPanel.DatasetFilesListPanel');
        $datasetFilesListPanel = new DatasetFilesListPanel(
            'datasetFiles',
            __('plugins.generic.dataverse.researchData.files'),
            [
                'apiUrl' => $apiUrl,
                'items' => $datasetFiles
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

    public function setupDatasetFileForm($request, $templateMgr, $study): void
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

        $serverFactory = new DataverseServerFactory();
        $contentId = $form->submissionContext->getId();
        $server = $serverFactory->createDataverseServer($contentId);

        $client = new NativeAPIClient($server);
        $service = new DataAPIService($client);

        $params = [
            'persistentUri' => $study->getPersistentUri(),
            'serverName' => $service->getDataverseServerName(),
            'serverUrl' => $server->getDataverseServerUrl(),
        ];

        $form->addField(new \PKP\components\forms\FieldHTML('researchData', [
            'description' => __("plugin.generic.dataverse.notification.submission.researchData", $params),
            'groupId' => 'default',
        ]));
    }
}
