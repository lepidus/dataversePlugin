<?php

import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');
import('plugins.generic.dataverse.dataverseAPI.DataverseClient');
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
            '<tab id="datasetTab" label="%s" :badge="researchDataCount">%s</tab>',
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

        $dataverseClient = new DataverseClient();
        try {
            $dataset = $dataverseClient->getDatasetActions()->get($study->getPersistentId());
            return $this->plugin->getTemplateResource('datasetTab/datasetData.tpl');
        } catch (DataverseException $e) {
            error_log('Dataverse API error: ' . $e->getMessage());
            $templateMgr = TemplateManager::getManager(Application::get()->getRequest());
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
        $user = $request->getUser();

        $metadataFormAction = $request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'datasets', null, null, ['submissionId' => $submission->getId()]);

        import('plugins.generic.dataverse.classes.factories.SubmissionDatasetFactory');
        $factory = new SubmissionDatasetFactory($submission);
        $dataset = $factory->getDataset();

        $fileListApiUrl = $request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'draftDatasetFiles', null, null, ['submissionId' => $submission->getId()]);

        $draftDatasetFiles = DAORegistry::getDAO('DraftDatasetFileDAO')->getBySubmissionId($submission->getId());

        $items = array_map(function ($draftDatasetFile) use ($props) {
            return $draftDatasetFile->getAllData();
        }, $draftDatasetFiles);

        ksort($items);

        $params = [
            'submissionId' => $submission->getId(),
            'userId' => $user->getId()
        ];

        $fileFormAction = $request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'draftDatasetFiles', null, null, $params);

        $this->initDatasetMetadataForm($templateMgr, $metadataFormAction, 'POST', $dataset);
        $this->initDatasetFilesList($templateMgr, $fileListApiUrl, $items);
        $this->initDatasetFileForm($templateMgr, $fileFormAction);
    }

    private function setupResearchDataUpdate(DataverseStudy $study): void
    {
        $request = Application::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);
        $context = $request->getContext();

        try {
            $dataverseClient = new DataverseClient();
            $dataset = $dataverseClient->getDatasetActions()->get($study->getPersistentId());

            $metadataFormAction = $request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'datasets/' . $study->getId());
            $fileListApiUrl = $request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'datasets/' . $study->getId() . '/files', null, null, ['persistentId' => $study->getPersistentId()]);
            $fileFormAction = $request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'datasets/' . $study->getId() . '/file');
            $items = array_map(function (DatasetFile $datasetFile) {
                return $datasetFile->getVars();
            }, $dataset->getFiles());

            $this->initDatasetMetadataForm($templateMgr, $metadataFormAction, 'PUT', $dataset);
            $this->initDatasetFilesList($templateMgr, $fileListApiUrl, $items);
            $this->initDatasetFileForm($templateMgr, $fileFormAction);

            $templateMgr->setState([
                'dataset' => $dataset,
                'deleteDatasetLabel' => __('plugins.generic.dataverse.researchData.delete'),
                'confirmDeleteDatasetMessage' => __('plugins.generic.dataverse.modal.confirmDatasetDelete'),
                'datasetCitationUrl' => $request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'datasets/' . $study->getId() . '/citation'),
            ]);
        } catch (DataverseException $e) {
            error_log('Dataverse API error: ' . $e->getMessage());
        }
    }

    private function initDatasetMetadataForm(PKPTemplateManager $templateMgr, string $action, string $method, Dataset $dataset): void
    {
        $context = Application::get()->getRequest()->getContext();

        $supportedFormLocales = $context->getSupportedFormLocales();
        $localeNames = AppLocale::getAllLocales();
        $locales = array_map(function ($localeKey) use ($localeNames) {
            return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
        }, $supportedFormLocales);

        $this->plugin->import('classes.form.DatasetMetadataForm');
        $datasetMetadataForm = new DatasetMetadataForm($action, $method, $locales, $dataset);

        $this->addComponent($templateMgr, $datasetMetadataForm);
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
                'modalTitle' => __('plugins.generic.dataverse.modal.addFile.title'),
                'title' => __('plugins.generic.dataverse.researchData'),
            ]
        );

        $this->addComponent($templateMgr, $datasetFilesListPanel);

        $templateMgr->setState([
            'deleteDatasetFileLabel' => __('plugins.generic.dataverse.modal.deleteDatasetFile'),
            'confirmDeleteMessage' => __('plugins.generic.dataverse.modal.confirmDelete')
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
        $dataverseClient = new DataverseClient();
        $rootDataverseCollection = $dataverseClient->getDataverseCollectionActions->getRoot();

        $configuration = DAORegistry::getDAO('DataverseConfigurationDAO')->get($form->submission->getContextId());

        $params = [
            'persistentUri' => $study->getPersistentUri(),
            'serverName' => $rootDataverseCollection->getName(),
            'serverUrl' => $configuration->getDataverseServerUrl(),
        ];

        $form->addField(new \PKP\components\forms\FieldHTML('researchData', [
            'description' => __("plugin.generic.dataverse.notification.submission.researchData", $params),
            'groupId' => 'default',
        ]));
    }
}
