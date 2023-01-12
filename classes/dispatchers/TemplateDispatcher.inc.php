<?php

import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');
import('plugins.generic.dataverse.classes.APACitation');
import('lib.pkp.classes.submission.SubmissionFile');
import('plugins.generic.dataverse.classes.study.DataverseStudyDAO');

class TemplateDispatcher extends DataverseDispatcher
{
    public function __construct(Plugin $plugin)
    {
        HookRegistry::register('Templates::Preprint::Details', array($this, 'addDataCitationSubmission'));
        HookRegistry::register('Template::Workflow::Publication', array($this, 'addDatasetDataToWorkflow'));
        HookRegistry::register('TemplateManager::display', array($this, 'loadResourceToWorkflow'));
        HookRegistry::register('PreprintHandler::view', array($this, 'loadResources'));

        parent::__construct($plugin);
    }

    public function addDataCitationSubmission(string $hookName, array $params): bool
    {
        $templateMgr =& $params[1];
        $output =& $params[2];

        $submission = $templateMgr->getTemplateVars('preprint');
        $dataverseStudyDao = DAORegistry::getDAO('DataverseStudyDAO');
        $study = $dataverseStudyDao->getStudyBySubmissionId($submission->getId());

        if (isset($study)) {
            $output .= $templateMgr->fetch($this->plugin->getTemplateResource('dataCitation.tpl'));
        }

        return false;
    }

    public function loadResources(string $hookName, array $params): bool
    {
        $request = $params[0];
        $submission = $params[1];
        $templateManager = TemplateManager::getManager($request);
        $pluginPath = $this->plugin->getPluginFullPath();

        $study = $this->getSubmissionStudy($submission);
        if (isset($study)) {
            $this->loadJavaScript($pluginPath, $templateManager);
            $this->addJavaScriptVariables($request, $templateManager, $study);
        }

        return false;
    }

    public function loadJavaScript($pluginPath, $templateManager)
    {
        $templateManager->addJavaScript(
            'dataverseScripts',
            $pluginPath . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'init.js',
            [
                'contexts' => ['backend', 'frontend']
            ]
        );
    }

    public function loadResourceToWorkflow(string $hookName, array $params): bool
    {
        $templateMgr = $params[0];
        $template = $params[1];


        if ($template == 'workflow/workflow.tpl' || $template == 'authorDashboard/authorDashboard.tpl') {
            $request = Application::get()->getRequest();

            $pluginPath = $request->getBaseUrl() . DIRECTORY_SEPARATOR . $this->plugin->getPluginPath();
            $submission = $templateMgr->get_template_vars('submission');

            $study = $this->getSubmissionStudy($submission);

            $templateMgr->addStyleSheet(
                'datasetData',
                $request->getBaseUrl() . '/' . $this->plugin->getPluginPath() . '/styles/datasetDataTab.css',
                [
                    'contexts' => ['backend']
                ]
            );

            if (!empty($study)) {
                $templateMgr->addJavaScript(
                    'dataverseHelper',
                    $pluginPath . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'dataverseHelper.js',
                    [
                        'inline' => false,
                        'contexts' => ['backend']
                    ]
                );

                $this->loadJavaScript($pluginPath, $templateMgr);
                $this->addJavaScriptVariables($request, $templateMgr, $study);

                $this->setupDatasetMetadataForm($request, $templateMgr, $study);
                $this->setupDatasetFilesList($request, $templateMgr, $study);
                $this->setupDatasetFileForm($request, $templateMgr, $study);
            }
        }
        return false;
    }

    public function addJavaScriptVariables($request, $templateManager, $study): void
    {
        $dispatcher = $request->getDispatcher();
        $context = $request->getContext();

        $configuration = $this->getDataverseConfiguration();
        $dataverseServer = $configuration->getDataverseServer();

        $dataverseNotificationMgr = new DataverseNotificationManager();
        $dataverseUrl = $configuration->getDataverseUrl();
        $params = ['dataverseUrl' => $dataverseUrl];
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

    public function addDatasetDataToWorkflow(string $hookName, array $params): bool
    {
        $templateMgr =& $params[1];
        $output =& $params[2];
        $context = Application::get()->getRequest()->getContext();
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

    private function getSubmissionStudy($submission): ?DataverseStudy
    {
        $dataverseStudyDao =& DAORegistry::getDAO('DataverseStudyDAO');
        $study = $dataverseStudyDao->getStudyBySubmissionId($submission->getId());
        return $study;
    }

    private function setupDatasetMetadataForm($request, $templateMgr, $study): void
    {
        $context = $request->getContext();
        $dispatcher = $request->getDispatcher();

        $apiUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), 'datasets/' . $study->getId());
        $vocabSuggestionUrlBase =$request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'vocabs', null, null, ['vocab' => 'submissionKeyword']);

        $datasetResponse = $this->getDataverseService()->getDatasetResponse($study);

        $supportedFormLocales = $context->getSupportedFormLocales();
        $localeNames = AppLocale::getAllLocales();
        $locales = array_map(function ($localeKey) use ($localeNames) {
            return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
        }, $supportedFormLocales);

        $this->plugin->import('classes.form.DatasetMetadataForm');
        $datasetMetadataForm = new DatasetMetadataForm($apiUrl, $locales, $datasetResponse, $vocabSuggestionUrlBase);

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
}
