<?php

import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');
import('plugins.generic.dataverse.classes.services.DatasetService');
import('lib.pkp.classes.log.SubmissionLog');
import('classes.log.SubmissionEventLogEntry');

class DataverseEventsDispatcher extends DataverseDispatcher
{
    protected function registerHooks(): void
    {
        HookRegistry::register('SubmissionHandler::saveSubmit', array($this, 'datasetDepositOnSubmission'));
        HookRegistry::register('Schema::get::draftDatasetFile', array($this, 'loadDraftDatasetFileSchema'));
        HookRegistry::register('Schema::get::submission', array($this, 'modifySubmissionSchema'));
        HookRegistry::register('LoadComponentHandler', array($this, 'setupDataverseHandlers'));
        HookRegistry::register('Dispatcher::dispatch', array($this, 'setupDataverseAPIHandlers'));
        HookRegistry::register('Publication::publish', array($this, 'publishDeposit'), HOOK_SEQUENCE_CORE);
        HookRegistry::register('EditorAction::recordDecision', array($this, 'publishInEditorAction'));
        HookRegistry::register('Form::config::before', array($this, 'addDatasetPublishNoticeInPost'));
        HookRegistry::register('promoteform::display', array($this, 'addDatasetPublishNoticeInEditorAction'));
        HookRegistry::register('initiateexternalreviewform::display', array($this, 'addSelectDataFilesForReview'));
        HookRegistry::register('initiateexternalreviewform::execute', array($this, 'saveSelectedDataFilesForReview'));
        HookRegistry::register('Publication::edit', array($this, 'updateDatasetOnPublicationUpdate'));
        HookRegistry::register('AcronPlugin::parseCronTab', [$this, 'addDataverseTasksToCrontab']);
    }

    public function modifySubmissionSchema(string $hookName, array $params): bool
    {
        $schema = &$params[0];
        $schema->properties->{'datasetSubject'} = (object) [
            'type' => 'string',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];
        $schema->properties->{'datasetLicense'} = (object) [
            'type' => 'string',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];
        $schema->properties->{'selectedDataFilesForReview'} = (object) [
            'type' => 'array',
            'items' => (object) [
                'type' => 'integer',
            ]
        ];

        return false;
    }

    public function datasetDepositOnSubmission(string $hookName, array $params): bool
    {
        $step = $params[0];
        $submission = $params[1];
        $stepForm = $params[2];

        if ($step !== 4 || !$stepForm->validate()) {
            return false;
        }

        $publication = $submission->getCurrentPublication();
        $dataStatementTypes = $publication->getData('dataStatementTypes');
        if (empty($dataStatementTypes) || !in_array(DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED, $dataStatementTypes)) {
            return false;
        }

        import('plugins.generic.dataverse.classes.factories.SubmissionDatasetFactory');
        $datasetFactory = new SubmissionDatasetFactory($submission);
        $dataset = $datasetFactory->getDataset();

        if (empty($dataset->getFiles())) {
            $stepForm->addError('researchDataRequired', __('plugins.generic.dataverse.error.researchDataRequired'));
            $stepForm->addErrorField('researchDataRequired');
            return false;
        }

        if (empty($dataset->getSubject())) {
            $stepForm->addError('researchDataRequired', __('plugins.generic.dataverse.error.datasetSubjectRequired'));
            $stepForm->addErrorField('researchDataRequired');
            return false;
        }

        $datasetService = new DatasetService();
        try {
            $datasetService->deposit($submission, $dataset);
        } catch (DataverseException $e) {
            $stepForm->addError(
                'depositError',
                __('plugins.generic.dataverse.error.depositFailedOnSubmission', ['error' => $e->getMessage()])
            );
            $stepForm->addErrorField('depositError');
        }

        return false;
    }

    public function publishDeposit(string $hookName, array $params): void
    {
        $submission = $params[2];
        $request = Application::get()->getRequest();

        $configuration = DAORegistry::getDAO('DataverseConfigurationDAO')->get($submission->getContextId());
        if ($configuration->getDatasetPublish() === DATASET_PUBLISH_SUBMISSION_ACCEPTED) {
            return;
        }

        $study = DAORegistry::getDAO('DataverseStudyDAO')->getStudyBySubmissionId($submission->getId());
        if (is_null($study)) {
            return;
        }

        $shouldPublish = $request->getUserVar('shouldPublishResearchData');
        if (!is_null($shouldPublish) && $shouldPublish == 0) {
            return;
        }

        $datasetService = new DatasetService();
        $datasetService->publish($study);
    }

    public function publishInEditorAction(string $hookName, array $params): void
    {
        $submission = $params[0];
        $decision = $params[1];
        $request = Application::get()->getRequest();

        if ($decision['decision'] !== SUBMISSION_EDITOR_DECISION_ACCEPT) {
            return;
        }

        $configuration = DAORegistry::getDAO('DataverseConfigurationDAO')->get($submission->getContextId());
        if ($configuration->getDatasetPublish() !== DATASET_PUBLISH_SUBMISSION_ACCEPTED) {
            return;
        }

        $study = DAORegistry::getDAO('DataverseStudyDAO')->getStudyBySubmissionId($submission->getId());
        if (is_null($study)) {
            return;
        }

        $shouldPublish = $request->getUserVar('shouldPublishResearchData');
        if (!is_null($shouldPublish) && $shouldPublish == 0) {
            return;
        }

        $datasetService = new DatasetService();
        $datasetService->publish($study);
    }

    public function addDatasetPublishNoticeInPost(string $hookName, \PKP\components\forms\FormComponent $form): void
    {
        if ($form->id !== 'publish' || !empty($form->errors)) {
            return;
        }

        $contextId = $form->submissionContext->getId();
        $configuration = DAORegistry::getDAO('DataverseConfigurationDAO')->get($contextId);
        if ($configuration->getDatasetPublish() === DATASET_PUBLISH_SUBMISSION_ACCEPTED) {
            return;
        }

        $submissionId = $form->publication->getData('submissionId');
        $study = DAORegistry::getDAO('DataverseStudyDAO')->getStudyBySubmissionId($submissionId);
        if (empty($study)) {
            return;
        }

        try {
            $dataverseClient = new DataverseClient();
            $dataset = $dataverseClient->getDatasetActions()->get($study->getPersistentId());

            if ($dataset->isPublished()) {
                return;
            }

            $rootDataverseCollection = $dataverseClient->getDataverseCollectionActions()->getRoot();
            $params = [
                'persistentUri' => $study->getPersistentUri(),
                'serverName' => $rootDataverseCollection->getName(),
                'serverUrl' => $configuration->getDataverseServerUrl(),
            ];

            $form->addField(new \PKP\components\forms\FieldHTML('researchDataNotice', [
                'label' => __('plugins.generic.dataverse.researchData'),
                'description' => __("plugins.generic.dataverse.researchData.publishNotice", $params),
                'groupId' => 'default'
            ]))
            ->addField(new \PKP\components\forms\FieldRadioInput('researchDataRadioInputs', [
                'label' => __('plugins.generic.dataverse.researchData.wouldLikeToPublish'),
                'name' => 'shouldPublishResearchData',
                'options' => [
                    ['value' => 1, 'label' => __('common.yes')],
                    ['value' => 0, 'label' => __('common.no')]
                ],
                'isRequired' => true,
                'groupId' => 'default'
            ]));
        } catch (DataverseException $e) {
            $warningIconHtml = '<span class="fa fa-exclamation-triangle pkpIcon--inline"></span>';
            $noticeMsg = __('plugins.generic.dataverse.notice.cannotPublish', ['error' => $e->getMessage()]);
            $msg = '<div class="pkpNotification pkpNotification--warning">' . $warningIconHtml . $noticeMsg . '</div>';

            $form->addField(new \PKP\components\forms\FieldHTML('researchDataNotice', [
                'description' => $msg,
                'groupId' => 'default',
            ]));
        }
    }

    public function addDatasetPublishNoticeInEditorAction(string $hookName, array $params): ?string
    {
        $form = &$params[0];
        $output = &$params[1];

        $request = PKPApplication::get()->getRequest();
        $context = $request->getContext();
        $templateMgr = TemplateManager::getManager($request);

        $submissionId = $templateMgr->get_template_vars('submissionId');
        $study = DAORegistry::getDAO('DataverseStudyDAO')->getStudyBySubmissionId($submissionId);
        if (empty($study)) {
            return null;
        }

        $configuration = DAORegistry::getDAO('DataverseConfigurationDAO')->get($context->getId());
        if ($configuration->getDatasetPublish() !== DATASET_PUBLISH_SUBMISSION_ACCEPTED) {
            return null;
        }

        try {
            $dataverseClient = new DataverseClient();
            $dataset = $dataverseClient->getDatasetActions()->get($study->getPersistentId());

            if ($dataset->isPublished()) {
                return null;
            }

            $rootDataverseCollection = $dataverseClient->getDataverseCollectionActions()->getRoot();
            $params = [
                'persistentUri' => $study->getPersistentUri(),
                'serverName' => $rootDataverseCollection->getName(),
                'serverUrl' => $configuration->getDataverseServerUrl(),
            ];
            $templateMgr->assign([
                'researchDataNotice' => __('plugins.generic.dataverse.researchData.publishNotice', $params),
                'canPublishResearchData' => true
            ]);
        } catch (DataverseException $e) {
            $templateMgr->assign([
                'researchDataNotice' => 'Dataverse Error: ' . $e->getMessage(),
                'canPublishResearchData' => false
            ]);
        }

        $templateOutput = $this->prepareFormToDisplay($templateMgr, $form, $request);
        $pattern = '/<div[^>]+id="promoteForm-step2[^>]+>/';

        if (preg_match($pattern, $templateOutput, $matches, PREG_OFFSET_CAPTURE)) {
            $match = $matches[0][0];
            $offset = $matches[0][1];
            $output = substr($templateOutput, 0, $offset + strlen($match));
            $output .= $templateMgr->fetch($this->plugin->getTemplateResource('editorActionPublish.tpl'));
            $output .= substr($templateOutput, $offset + strlen($match));
        }

        $fbv = $templateMgr->getFBV();
        $fbv->setForm(null);

        return $output;
    }

    public function addSelectDataFilesForReview(string $hookName, array $params): ?string
    {
        $form = &$params[0];
        $output = &$params[1];

        $request = PKPApplication::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);

        $submissionId = $templateMgr->get_template_vars('submissionId');
        $study = DAORegistry::getDAO('DataverseStudyDAO')->getStudyBySubmissionId($submissionId);
        if (empty($study)) {
            return null;
        }

        try {
            $dataverseClient = new DataverseClient();
            $dataset = $dataverseClient->getDatasetActions()->get($study->getPersistentId());
            $datasetFiles = $dataverseClient->getDatasetFileActions()->getByDatasetId($study->getPersistentId());

            if ($dataset->isPublished()) {
                return null;
            }

            $templateMgr->assign('datasetFiles', $datasetFiles);
        } catch (DataverseException $e) {
            $templateMgr->assign([
                'dataverseError' => 'Dataverse Error: ' . $e->getMessage(),
            ]);
        }

        $templateOutput = $this->prepareFormToDisplay($templateMgr, $form, $request);
        $pattern = '/<p>'.__('editor.submission.externalReviewDescription').'<\/p>/';

        if (preg_match($pattern, $templateOutput, $matches, PREG_OFFSET_CAPTURE)) {
            $match = $matches[0][0];
            $offset = $matches[0][1];
            $output = substr($templateOutput, 0, $offset);
            $output .= $templateMgr->fetch($this->plugin->getTemplateResource('selectDataFilesForReview.tpl'));
            $output .= substr($templateOutput, $offset);
        }

        $fbv = $templateMgr->getFBV();
        $fbv->setForm(null);

        return $output;
    }

    public function saveSelectedDataFilesForReview(string $hookName, array $params)
    {
        $form = &$params[0];
        $submission = &$form->_submission;

        $request = Application::get()->getRequest();
        $selectedFiles = $request->getUserVar('selectedDataFilesForReview');

        if (!is_null($selectedFiles)) {
            $submission = Services::get('submission')->edit(
                $submission,
                ['selectedDataFilesForReview' => $selectedFiles],
                $request
            );
        }
    }

    public function updateDatasetOnPublicationUpdate(string $hookName, array $args): bool
    {
        $publication = &$args[0];
        $data = [];

        $publicationDAO = DAORegistry::getDAO('PublicationDAO');
        $publicationDAO->updateObject($publication);

        $submission = Services::get('submission')->get($publication->getData('submissionId'));

        $studyDAO = DAORegistry::getDAO('DataverseStudyDAO');
        $study = $studyDAO->getStudyBySubmissionId($submission->getId());

        if (!$study) {
            return false;
        }

        import('plugins.generic.dataverse.classes.APACitation');
        $apaCitation = new APACitation();

        $data['persistentId'] = $study->getPersistentId();
        $data['pubCitation'] = $apaCitation->getFormattedCitationBySubmission($submission);

        $datasetService = new DatasetService();
        $datasetService->update($data);

        return false;
    }

    private function prepareFormToDisplay($templateMgr, $form, $request): string
    {
        $context = $request->getContext();
        $templateMgr->setCacheability(CACHEABILITY_NO_STORE);

        $fbv = $templateMgr->getFBV();
        $fbv->setForm($form);

        $templateMgr->assign(array_merge($form->_data, [
            'isError' => !$form->isValid(),
            'errors' => $form->getErrorsArray(),
            'formLocales' => $form->supportedLocales,
            'formLocale' => $form->getDefaultFormLocale(),
        ]));

        if (!$templateMgr->getTemplateVars('primaryLocale')) {
            $templateMgr->assign([
                'primaryLocale' => $context
                    ? $context->getPrimaryLocale()
                    : (Config::getVar('general', 'installed') ? $request->getSite()->getPrimaryLocale() : null),
            ]);
        }

        return $templateMgr->fetch($form->_template);
    }

    public function setupDataverseAPIHandlers(string $hookname, Request $request): void
    {
        $router = $request->getRouter();
        if (!($router instanceof \APIRouter)) {
            return;
        }

        if (str_contains($request->getRequestPath(), 'api/v1/datasets')) {
            $this->plugin->import('api.v1.datasets.DatasetHandler');
            $handler = new DatasetHandler();
        } elseif (str_contains($request->getRequestPath(), 'api/v1/draftDatasetFiles')) {
            $this->plugin->import('api.v1.draftDatasetFiles.DraftDatasetFileHandler');
            $handler = new DraftDatasetFileHandler();
        } elseif (str_contains($request->getRequestPath(), 'api/v1/dataverse')) {
            $this->plugin->import('api.v1.dataverse.DataverseHandler');
            $handler = new DataverseHandler();
        }

        if (!isset($handler)) {
            return;
        }

        $router->setHandler($handler);
        $handler->getApp()->run();
        exit;
    }

    public function loadDraftDatasetFileSchema($hookname, $params): bool
    {
        $schema = &$params[0];
        $draftDatasetFileSchemaFile = BASE_SYS_DIR . '/plugins/generic/dataverse/schemas/draftDatasetFile.json';

        if (file_exists($draftDatasetFileSchemaFile)) {
            $schema = json_decode(file_get_contents($draftDatasetFileSchemaFile));
            if (!$schema) {
                fatalError('Schema failed to decode. This usually means it is invalid JSON. Requested: ' . $draftDatasetFileSchemaFile . '. Last JSON error: ' . json_last_error());
            }
        }

        return false;
    }

    public function setupDataverseHandlers($hookName, $params): bool
    {
        $component = &$params[0];
        $ourHandlers = [
            'plugins.generic.dataverse.controllers.grid.DraftDatasetFileGridHandler',
            'plugins.generic.dataverse.controllers.grid.DatasetReviewGridHandler'
        ];
        if (in_array($component, $ourHandlers)) {
            import($component);
            return true;
        }
        return false;
    }

    public function addDataverseTasksToCrontab($hookName, $params)
    {
        $taskFilesPath = &$params[0];
        $taskFilesPath[] = $this->plugin->getPluginPath() . DIRECTORY_SEPARATOR . 'scheduledTasks.xml';
        return false;
    }
}
