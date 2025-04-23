<?php

use PKP\components\forms\FormComponent;

import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');
import('plugins.generic.dataverse.dataverseAPI.DataverseClient');
import('lib.pkp.classes.submission.SubmissionFile');

class DatasetTabDispatcher extends DataverseDispatcher
{
    protected function registerHooks(): void
    {
        HookRegistry::register('Template::Workflow::Publication', array($this, 'addResearchDataTab'));
        HookRegistry::register('TemplateManager::display', array($this, 'loadResourcesToWorkflow'));
    }

    private function getSubmissionStudy(int $submissionId): ?DataverseStudy
    {
        $dataverseStudyDao = &DAORegistry::getDAO('DataverseStudyDAO');
        $study = $dataverseStudyDao->getStudyBySubmissionId($submissionId);
        return $study;
    }

    public function addResearchDataTab(string $hookName, array $params): bool
    {
        $templateMgr = &$params[1];
        $output = &$params[2];

        $submission = $templateMgr->getTemplateVars('submission');
        $study = $this->getSubmissionStudy($submission->getId());
        $tabTemplate = $this->plugin->getTemplateResource('datasetTab/noResearchData.tpl');

        if (!is_null($study)) {
            $tabTemplate = $this->plugin->getTemplateResource('datasetTab/datasetData.tpl');
        }

        $configurationDAO = DAORegistry::getDAO('DataverseConfigurationDAO');
        $configuration = $configurationDAO->get($submission->getData('contextId'));
        $additionalInstructions = $configuration->getLocalizedData('additionalInstructions');
        $templateMgr->assign('dataverseAdditionalInstructions', $additionalInstructions);

        $output .= sprintf(
            '<tab id="datasetTab" label="%s" :badge="researchDataCount">%s</tab>',
            __("plugins.generic.dataverse.researchData"),
            $templateMgr->fetch($tabTemplate)
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

        $submission = $templateMgr->getTemplateVars('submission');
        $study = $this->getSubmissionStudy($submission->getId());

        if (is_null($study)) {
            $this->setupResearchDataDeposit($submission);
            return false;
        }

        $this->setupResearchDataUpdate($submission, $study);

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

        $items = array_map(function ($draftDatasetFile) {
            return $draftDatasetFile->getAllData();
        }, $draftDatasetFiles);

        ksort($items);

        $params = [
            'submissionId' => $submission->getId(),
            'userId' => $user->getId()
        ];

        $fileFormAction = $request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'draftDatasetFiles', null, null, $params);
        $dataversePluginApiUrl = $request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'dataverse');

        $this->initDatasetMetadataForm($templateMgr, $metadataFormAction, 'POST', $dataset);
        $this->initDatasetFilesList($templateMgr, $fileListApiUrl, $items);
        $this->initDatasetFileForm($templateMgr, $fileFormAction);

        $templateMgr->setState([
            'dataversePluginApiUrl' => $dataversePluginApiUrl,
            'hasDepositedDataset' => false
        ]);
    }

    private function setupResearchDataUpdate(Submission $submission, DataverseStudy $study): void
    {
        $request = Application::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);
        $context = $request->getContext();
        $dispatcher = $request->getDispatcher();
        $router = $request->getRouter();
        $userRoles = (array) $router->getHandler()->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
        $configuration = DAORegistry::getDAO('DataverseConfigurationDAO')->get($context->getId());

        $supportedFormLocales = $context->getSupportedFormLocales();
        $localeNames = AppLocale::getAllLocales();
        $locales = array_map(function ($localeKey) use ($localeNames) {
            return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
        }, $supportedFormLocales);

        $dataversePluginApiUrl = $dispatcher->url(
            $request,
            ROUTE_API,
            $context->getPath(),
            'dataverse'
        );
        $datasetApiUrl = $dispatcher->url(
            $request,
            ROUTE_API,
            $context->getPath(),
            'datasets/' . $study->getId()
        );
        $fileListApiUrl = $dispatcher->url(
            $request,
            ROUTE_API,
            $context->getPath(),
            'datasets/' . $study->getId() . '/files',
            null,
            null,
            ['persistentId' => $study->getPersistentId()]
        );
        $fileFormAction = $dispatcher->url(
            $request,
            ROUTE_API,
            $context->getPath(),
            'datasets/' . $study->getId() . '/file'
        );
        $dataStatementUrl = $dispatcher->url(
            $request,
            ROUTE_PAGE,
            null,
            'authorDashboard',
            'submission',
            $submission->getId(),
            null,
            '#publication/dataStatement'
        );

        import('lib.pkp.classes.mail.MailTemplate');
        $mail = new MailTemplate('DATASET_DELETE_NOTIFICATION', null, $context, false);
        $mail->assignParams([
            'submissionTitle' => htmlspecialchars($submission->getLocalizedFullTitle()),
            'dataStatementUrl' => $dataStatementUrl,
        ]);
        $mail->replaceParams();

        $this->initDatasetMetadataForm($templateMgr, $datasetApiUrl, 'PUT');
        $this->initDatasetFilesList($templateMgr, $fileListApiUrl, []);
        $this->initDatasetFileForm($templateMgr, $fileFormAction);

        $deleteDatasetForm = $this->getDeleteDatasetForm($datasetApiUrl, $context, $locales, $mail);
        $this->addComponent($templateMgr, $deleteDatasetForm);

        $templateMgr->setState([
            'dataversePluginApiUrl' => $dataversePluginApiUrl,
            'deleteDatasetLabel' => __('plugins.generic.dataverse.researchData.delete'),
            'confirmDeleteDatasetMessage' => __('plugins.generic.dataverse.modal.confirmDatasetDelete'),
            'publishDatasetLabel' => __('plugins.generic.dataverse.researchData.publish'),
            'confirmPublishDatasetMessage' => __('plugins.generic.dataverse.modal.confirmDatasetPublish', [
                'serverUrl' => $configuration->getDataverseServerUrl(),
            ]),
            'loadingCitationMsg' => __('plugins.generic.dataverse.metadataForm.loadingDatasetCitation'),
            'datasetCitationUrl' => $dispatcher->url($request, ROUTE_API, $context->getPath(), 'datasets/' . $study->getId() . '/citation'),
            'canSendEmail' => in_array(ROLE_ID_MANAGER, $userRoles),
            'hasDepositedDataset' => true
        ]);
    }

    private function initDatasetMetadataForm(PKPTemplateManager $templateMgr, string $action, string $method, ?Dataset $dataset = null): void
    {
        $context = Application::get()->getRequest()->getContext();

        $supportedFormLocales = $context->getSupportedFormLocales();
        $localeNames = AppLocale::getAllLocales();
        $locales = array_map(function ($localeKey) use ($localeNames) {
            return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
        }, $supportedFormLocales);

        $this->plugin->import('classes.components.forms.DatasetMetadataForm');
        $datasetMetadataForm = new DatasetMetadataForm($action, $method, $locales, $dataset);

        $this->addComponent($templateMgr, $datasetMetadataForm);
    }

    private function initDatasetFilesList($templateMgr, $apiUrl, $items): void
    {
        $this->plugin->import('classes.components.listPanel.DatasetFilesListPanel');
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

        $this->plugin->import('classes.components.forms.DraftDatasetFileForm');
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

    public function getDeleteDatasetForm(
        string $apiUrl,
        Context $context,
        array $locales,
        MailTemplate $mail
    ): FormComponent {
        $deleteDatasetForm = new FormComponent('deleteDataset', 'DELETE', $apiUrl, $locales);

        $deleteDatasetForm->addPage([
            'id' => 'default',
            'submitButton' => [
                'label' => __('plugins.generic.dataverse.researchData.delete.submitLabel'),
            ],
        ])->addGroup([
            'id' => 'default',
            'pageId' => 'default',
        ])->addField(new \PKP\components\forms\FieldOptions('sendDeleteEmail', [
            'label' => __('common.sendEmail'),
            'type' => 'radio',
            'options' => [
                ['value' => 1, 'label' => __('plugins.generic.dataverse.researchData.delete.sendEmail.yes')],
                ['value' => 0, 'label' => __('plugins.generic.dataverse.researchData.delete.sendEmail.no')],
            ],
            'value' => 1,
            'groupId' => 'default'
        ]))->addField(new \PKP\components\forms\FieldRichTextarea('deleteMessage', [
            'label' => __('plugins.generic.dataverse.researchData.delete.emailNotification'),
            'value' => $mail->getBody(),
            'showWhen' => ['sendDeleteEmail', 1],
            'groupId' => 'default'
        ]));

        return $deleteDatasetForm;
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
}
