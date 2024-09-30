<?php

namespace APP\plugins\generic\dataverse\classes\dispatchers;

use PKP\plugins\Hook;
use APP\core\Application;
use APP\template\TemplateManager;
use APP\submission\Submission;
use PKP\db\DAORegistry;
use PKP\security\Role;
use PKP\facades\Locale;
use APP\plugins\generic\dataverse\classes\dispatchers\DataverseDispatcher;
use APP\plugins\generic\dataverse\classes\dataverseStudy\DataverseStudy;
use APP\plugins\generic\dataverse\classes\entities\Dataset;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\classes\components\forms\DatasetMetadataForm;
use APP\plugins\generic\dataverse\classes\components\listPanel\DatasetFilesListPanel;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use APP\plugins\generic\dataverse\classes\factories\SubmissionDatasetFactory;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use PKP\components\forms\FormComponent;

class DatasetTabDispatcher extends DataverseDispatcher
{
    protected function registerHooks(): void
    {
        Hook::add('Template::Workflow::Publication', [$this, 'addResearchDataTab']);
        Hook::add('TemplateManager::display', [$this, 'loadResourcesToWorkflow']);
    }

    private function getSubmissionStudy(int $submissionId): ?DataverseStudy
    {
        return Repo::dataverseStudy()->getBySubmissionId($submissionId);
    }

    public function addResearchDataTab(string $hookName, array $params): bool
    {
        $templateMgr = &$params[1];
        $output = &$params[2];

        $submission = $templateMgr->getTemplateVars('submission');

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

        return $this->plugin->getTemplateResource('datasetTab/datasetData.tpl');
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
                'priority' => TemplateManager::STYLE_SEQUENCE_LAST,
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

        $dataversePluginApiUrl = $request->getDispatcher()->url($request, Application::ROUTE_API, $context->getPath(), 'dataverse');
        $metadataFormAction = $request->getDispatcher()->url($request, Application::ROUTE_API, $context->getPath(), 'datasets', null, null, ['submissionId' => $submission->getId()]);
        $fileListApiUrl = $request
            ->getDispatcher()
            ->url($request, Application::ROUTE_API, $context->getPath(), 'draftDatasetFiles', null, null, ['submissionId' => $submission->getId()]);
        $fileActionApiUrl = $request
            ->getDispatcher()
            ->url($request, Application::ROUTE_API, $context->getPath(), 'draftDatasetFiles');

        $factory = new SubmissionDatasetFactory($submission);
        $dataset = $factory->getDataset();
        $draftDatasetFiles = Repo::draftDatasetFile()->getBySubmissionId($submission->getId())->toArray();

        $datasetFiles = array_map(function ($draftDatasetFile) use ($fileActionApiUrl) {
            $fileVars = $draftDatasetFile->getAllData();
            $fileVars['downloadUrl'] = $fileActionApiUrl . '/' . $draftDatasetFile->getFileId() . '/download';
            return $fileVars;
        }, $draftDatasetFiles);
        ksort($datasetFiles);

        $this->initDatasetMetadataForm($templateMgr, $metadataFormAction, 'POST', $dataset);
        $this->initDatasetFilesList($templateMgr, $submission, [
            'dataversePluginApiUrl' => $dataversePluginApiUrl,
            'fileListApiUrl' => $fileListApiUrl,
            'fileActionApiUrl' => $fileActionApiUrl,
            'files' => $datasetFiles
        ]);

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
        $userRoles = (array) $router->getHandler()->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        $configuration = DAORegistry::getDAO('DataverseConfigurationDAO')->get($context->getId());

        $dataversePluginApiUrl = $dispatcher->url(
            $request,
            Application::ROUTE_API,
            $context->getPath(),
            'dataverse'
        );
        $datasetApiUrl = $dispatcher->url(
            $request,
            Application::ROUTE_API,
            $context->getPath(),
            'datasets/' . $study->getId()
        );
        $fileListApiUrl = $dispatcher->url(
            $request,
            Application::ROUTE_API,
            $context->getPath(),
            'datasets/' . $study->getId() . '/files'
        );
        $fileActionApiUrl = $dispatcher->url(
            $request,
            Application::ROUTE_API,
            $context->getPath(),
            'datasets/' . $study->getId() . '/file'
        );
        $datasetStatementUrl = $dispatcher->url(
            $request,
            Application::ROUTE_PAGE,
            null,
            'authorDashboard',
            'submission',
            $submission->getId(),
            null,
            '#publication/dataStatement'
        );

        $this->initDatasetMetadataForm($templateMgr, $datasetApiUrl, 'PUT');
        $this->initDatasetFilesList($templateMgr, $submission, [
            'dataversePluginApiUrl' => $dataversePluginApiUrl,
            'fileListApiUrl' => $fileListApiUrl,
            'fileActionApiUrl' => $fileActionApiUrl,
            'files' => []
        ]);

        $defaultEmailBody = $this->getDeleteDatasetEmailBody($submission, $datasetStatementUrl);
        $deleteDatasetForm = $this->getDeleteDatasetForm($context, $datasetApiUrl, $defaultEmailBody);
        $this->addComponent($templateMgr, $deleteDatasetForm);

        $templateMgr->setState([
            'dataversePluginApiUrl' => $dataversePluginApiUrl,
            'deleteDatasetLabel' => __('plugins.generic.dataverse.researchData.delete'),
            'confirmDeleteDatasetMessage' => __('plugins.generic.dataverse.modal.confirmDatasetDelete'),
            'publishDatasetLabel' => __('plugins.generic.dataverse.researchData.publish'),
            'confirmPublishDatasetMessage' => __('plugins.generic.dataverse.modal.confirmDatasetPublish', [
                'serverUrl' => $configuration->getDataverseServerUrl(),
            ]),
            'datasetCitationUrl' => $dispatcher->url($request, Application::ROUTE_API, $context->getPath(), 'datasets/' . $study->getId() . '/citation'),
            'canSendEmail' => in_array(Role::ROLE_ID_MANAGER, $userRoles),
            'hasDepositedDataset' => true
        ]);
    }

    private function initDatasetMetadataForm(TemplateManager $templateMgr, string $action, string $method, ?Dataset $dataset = null): void
    {
        $datasetMetadataForm = new DatasetMetadataForm($action, $method, $dataset, 'workflow');
        $this->addComponent($templateMgr, $datasetMetadataForm);
    }

    private function initDatasetFilesList($templateMgr, $submission, $args): void
    {
        $templateMgr->addJavaScript(
            'dataset-files-list-panel',
            $this->plugin->getPluginFullPath() . '/js/ui/components/DatasetFilesListPanel.js',
            [
                'priority' => TemplateManager::STYLE_SEQUENCE_LAST,
                'contexts' => ['backend']
            ]
        );

        $datasetFilesListPanel = new DatasetFilesListPanel(
            'datasetFiles',
            __('plugins.generic.dataverse.researchData.files'),
            $submission,
            [
                'addFileLabel' => __('plugins.generic.dataverse.addResearchData'),
                'dataversePluginApiUrl' => $args['dataversePluginApiUrl'],
                'fileListUrl' => $args['fileListApiUrl'],
                'fileActionUrl' => $args['fileActionApiUrl'],
                'items' => $args['files'],
                'modalTitle' => __('plugins.generic.dataverse.modal.addFile.title'),
                'title' => __('plugins.generic.dataverse.researchData'),
            ]
        );

        $this->addComponent($templateMgr, $datasetFilesListPanel);
    }

    private function getDeleteDatasetEmailBody($submission, $datasetStatementUrl): string
    {
        return __(
            'emails.datasetDeleteNotification.body',
            [
                'submissionTitle' => htmlspecialchars($submission->getLocalizedFullTitle()),
                'dataStatementUrl' => $datasetStatementUrl,
            ]
        );
    }

    public function getDeleteDatasetForm($context, $datasetApiUrl, $defaultEmailBody): FormComponent
    {
        $locales = $this->getFormLocales($context);
        $deleteDatasetForm = new FormComponent('deleteDataset', 'DELETE', $datasetApiUrl, $locales);
        $deleteDatasetForm->addPage([
            'id' => 'default',
            'submitButton' => [
                'label' => __('plugins.generic.dataverse.researchData.delete.submitLabel'),
            ],
        ])->addGroup([
            'id' => 'default',
            'pageId' => 'default',
        ])->addField(new \PKP\components\forms\FieldRichTextarea('deleteMessage', [
            'label' => __('plugins.generic.dataverse.researchData.delete.emailNotification'),
            'value' => $defaultEmailBody,
            'groupId' => 'default'
        ]));

        return $deleteDatasetForm;
    }

    private function getFormLocales($context): array
    {
        $supportedFormLocales = $context->getSupportedFormLocales();
        $localeNames = array_map(fn ($localeMetadata) => $localeMetadata->getDisplayName(), Locale::getLocales());

        $formLocales = array_map(function ($localeKey) use ($localeNames) {
            return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
        }, $supportedFormLocales);

        return $formLocales;
    }

    private function addComponent(TemplateManager $templateMgr, $component, $args = []): void
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
