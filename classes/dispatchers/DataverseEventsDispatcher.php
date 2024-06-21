<?php

namespace APP\plugins\generic\dataverse\classes\dispatchers;

use PKP\plugins\Hook;
use APP\core\Application;
use PKP\db\DAORegistry;
use APP\decision\Decision;
use PKP\decision\steps\Form;
use PKP\components\forms\FormComponent;
use Illuminate\Support\Facades\Event;
use APP\plugins\generic\dataverse\api\v1\datasets\DatasetHandler;
use APP\plugins\generic\dataverse\api\v1\draftDatasetFiles\DraftDatasetFileHandler;
use APP\plugins\generic\dataverse\classes\APACitation;
use APP\plugins\generic\dataverse\classes\components\forms\SelectDataFilesForReviewForm;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;
use APP\plugins\generic\dataverse\classes\dispatchers\DataverseDispatcher;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\classes\observers\listeners\DatasetDepositOnSubmission;
use APP\plugins\generic\dataverse\classes\observers\listeners\ProcessDataverseDecisionsActions;
use APP\plugins\generic\dataverse\classes\services\DatasetService;
use APP\plugins\generic\dataverse\controllers\grid\DatasetReviewGridHandler;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;

class DataverseEventsDispatcher extends DataverseDispatcher
{
    protected function registerHooks(): void
    {
        Event::subscribe(new DatasetDepositOnSubmission());
        Event::subscribe(new ProcessDataverseDecisionsActions());

        Hook::add('Schema::get::draftDatasetFile', [$this, 'loadDraftDatasetFileSchema']);
        Hook::add('Dispatcher::dispatch', [$this, 'setupDataverseAPIHandlers']);
        Hook::add('Schema::get::submission', [$this, 'modifySubmissionSchema']);
        Hook::add('Form::config::before', [$this, 'addDatasetPublishNoticeInPublishing']);
        Hook::add('Publication::publish', [$this, 'publishDeposit'], Hook::SEQUENCE_CORE);
        Hook::add('TemplateManager::display', [$this, 'editDecisions']);
        Hook::add('LoadComponentHandler', [$this, 'setupDataverseComponentHandlers']);
        Hook::add('Publication::edit', [$this, 'updateDatasetOnPublicationUpdate']);
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

    private function addDatasetPublishFieldsToForm(FormComponent $form, $params)
    {
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

            $this->addDatasetPublishFieldsToForm($form, $params);
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
        $datasetService->publish($submission, $study);
    }

    public function editDecisions(string $hookName, array $params): void
    {
        $templateMgr = $params[0];
        $template = $params[1];

        if ($template != 'decision/record.tpl') {
            return;
        }

        $submission = $templateMgr->getTemplateVars('submission');
        $study = Repo::dataverseStudy()->getBySubmissionId($submission->getId());
        if (empty($study)) {
            return;
        }

        $decision = $templateMgr->getState('decision');
        if ($decision == Decision::EXTERNAL_REVIEW) {
            $this->editSendForReviewDecision($templateMgr, $study);
        }

        if ($decision == Decision::ACCEPT) {
            $this->editAcceptDecision($templateMgr, $study, $submission->getData('contextId'));
        }
    }

    private function editSendForReviewDecision($templateMgr, $study)
    {
        try {
            $dataverseClient = new DataverseClient();
            $dataset = $dataverseClient->getDatasetActions()->get($study->getPersistentId());
            $datasetFiles = $dataverseClient->getDatasetFileActions()->getByDatasetId($study->getPersistentId());

            if ($dataset->isPublished()) {
                return;
            }
        } catch (DataverseException $e) {
            $templateMgr->assign([
                'dataverseError' => 'Dataverse Error: ' . $e->getMessage(),
            ]);
            return;
        }

        $decisionSteps = $templateMgr->getState('steps');
        $selectDataFilesForm = new SelectDataFilesForReviewForm($datasetFiles);
        $decisionStepForm = new Form(
            'selectDataFiles',
            __('plugins.generic.dataverse.decision.selectDataFiles.name'),
            __('plugins.generic.dataverse.decision.selectDataFiles.description'),
            $selectDataFilesForm
        );
        $decisionSteps[] = $decisionStepForm->getState();

        $templateMgr->setState(['steps' => $decisionSteps]);
    }

    private function editAcceptDecision($templateMgr, $study, $contextId)
    {
        $configuration = DAORegistry::getDAO('DataverseConfigurationDAO')->get($contextId);
        if ($configuration->getDatasetPublish() != DataverseConfiguration::DATASET_PUBLISH_SUBMISSION_ACCEPTED) {
            return;
        }

        try {
            $dataverseClient = new DataverseClient();
            $dataset = $dataverseClient->getDatasetActions()->get($study->getPersistentId());

            if ($dataset->isPublished()) {
                return;
            }

            $rootDataverseCollection = $dataverseClient->getDataverseCollectionActions()->getRoot();
        } catch (DataverseException $e) {
            $templateMgr->assign([
                'dataverseError' => 'Dataverse Error: ' . $e->getMessage(),
            ]);
            return;
        }

        $params = [
            'persistentUri' => $study->getPersistentUri(),
            'serverName' => $rootDataverseCollection->getName(),
            'serverUrl' => $configuration->getDataverseServerUrl(),
        ];

        $decisionSteps = $templateMgr->getState('steps');
        $datasetPublishForm = new FormComponent('datasetPublish', '', FormComponent::ACTION_EMIT, []);
        $this->addDatasetPublishFieldsToForm($datasetPublishForm, $params);
        $decisionStepForm = new Form(
            'researchDataPublishNotice',
            __('plugins.generic.dataverse.researchData'),
            '',
            $datasetPublishForm
        );
        $decisionSteps[] = $decisionStepForm->getState();

        $templateMgr->setState(['steps' => $decisionSteps]);
    }

    public function updateDatasetOnPublicationUpdate(string $hookName, array $params): bool
    {
        $publication = &$params[0];
        $submission = Repo::submission()->get($publication->getData('submissionId'));
        $data = [];

        $study = Repo::dataverseStudy()->getBySubmissionId($submission->getId());
        if (!$study) {
            return false;
        }

        $apaCitation = new APACitation();
        $data['persistentId'] = $study->getPersistentId();
        $data['pubCitation'] = $apaCitation->getFormattedCitationBySubmission($submission, $publication);

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

    public function setupDataverseComponentHandlers($hookName, $params): bool
    {
        $component = &$params[0];
        $componentInstance = &$params[2];

        if ($component == 'plugins.generic.dataverse.controllers.grid.DatasetReviewGridHandler') {
            $componentInstance = new DatasetReviewGridHandler();
            return true;
        }
        return false;
    }
}
