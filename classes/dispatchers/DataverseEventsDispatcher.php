<?php

namespace APP\plugins\generic\dataverse\classes\dispatchers;

use PKP\plugins\Hook;
use APP\core\Application;
use PKP\db\DAORegistry;
use PKP\components\forms\FormComponent;
use Illuminate\Support\Facades\Event;
use APP\plugins\generic\dataverse\api\v1\datasets\DatasetHandler;
use APP\plugins\generic\dataverse\api\v1\draftDatasetFiles\DraftDatasetFileHandler;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;
use APP\plugins\generic\dataverse\classes\dispatchers\DataverseDispatcher;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\classes\observers\listeners\DatasetDepositOnSubmission;
use APP\plugins\generic\dataverse\classes\services\DatasetService;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;

class DataverseEventsDispatcher extends DataverseDispatcher
{
    protected function registerHooks(): void
    {
        Event::subscribe(new DatasetDepositOnSubmission());

        Hook::add('Schema::get::draftDatasetFile', [$this, 'loadDraftDatasetFileSchema']);
        Hook::add('Dispatcher::dispatch', [$this, 'setupDataverseAPIHandlers']);
        Hook::add('Schema::get::submission', [$this, 'modifySubmissionSchema']);
        Hook::add('Form::config::before', [$this, 'addDatasetPublishNoticeInPublishing']);
        Hook::add('Publication::publish', [$this, 'publishDeposit'], Hook::SEQUENCE_CORE);
        //Hook::add('LoadComponentHandler', [$this, 'setupDataverseHandlers']);
        // HookRegistry::register('EditorAction::recordDecision', array($this, 'publishInEditorAction'));
        // HookRegistry::register('promoteform::display', array($this, 'addDatasetPublishNoticeInEditorAction'));
        // HookRegistry::register('initiateexternalreviewform::display', array($this, 'addSelectDataFilesForReview'));
        // HookRegistry::register('initiateexternalreviewform::execute', array($this, 'saveSelectedDataFilesForReview'));
        // HookRegistry::register('Publication::edit', array($this, 'updateDatasetOnPublicationUpdate'));
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

    public function addDatasetPublishNoticeInPublishing(string $hookName, FormComponent $form): void
    {
        if ($form->id !== 'publish' || !empty($form->errors)) {
            return;
        }

        $contextId = $form->submissionContext->getId();
        $configuration = DAORegistry::getDAO('DataverseConfigurationDAO')->get($contextId);
        if ($configuration->getDatasetPublish() === DataverseConfiguration::DATASET_PUBLISH_SUBMISSION_ACCEPTED) {
            return;
        }

        $submissionId = $form->publication->getData('submissionId');
        $study = Repo::dataverseStudy()->getBySubmissionId($submissionId);
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

    public function publishDeposit(string $hookName, array $params): void
    {
        $submission = $params[2];
        $request = Application::get()->getRequest();

        $configuration = DAORegistry::getDAO('DataverseConfigurationDAO')->get($submission->getContextId());
        if ($configuration->getDatasetPublish() === DataverseConfiguration::DATASET_PUBLISH_SUBMISSION_ACCEPTED) {
            return;
        }

        $study = Repo::dataverseStudy()->getBySubmissionId($submission->getId());
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

    public function setupDataverseAPIHandlers(string $hookname, array $params): void
    {
        $request = $params[0];
        $router = $request->getRouter();

        if (!($router instanceof \PKP\core\APIRouter)) {
            return;
        }

        if (str_contains($request->getRequestPath(), 'api/v1/datasets')) {
            $handler = new DatasetHandler();
        } elseif (str_contains($request->getRequestPath(), 'api/v1/draftDatasetFiles')) {
            $handler = new DraftDatasetFileHandler();
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
        $componentInstance = &$params[2];

        if ($component == 'plugins.generic.dataverse.controllers.grid.DraftDatasetFileGridHandler') {
            $componentInstance = new DraftDatasetFileGridHandler();
            return true;
        }
        return false;
    }
}