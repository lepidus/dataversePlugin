<?php

namespace APP\plugins\generic\dataverse\classes\dispatchers;

use PKP\plugins\Hook;
use APP\core\Application;
use APP\template\TemplateManager;
use APP\pages\submission\SubmissionHandler;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\classes\dispatchers\DataverseDispatcher;
use APP\plugins\generic\dataverse\classes\services\DataStatementService;
use APP\plugins\generic\dataverse\classes\components\forms\DataStatementForm;

class DataStatementDispatcher extends DataverseDispatcher
{
    public function registerHooks(): void
    {
        Hook::add('TemplateManager::setupBackendPage', [$this, 'addDataStatementResourcesToBackend']);
        Hook::add('TemplateManager::display', [$this, 'addDataStatementResources']);
        Hook::add('TemplateManager::display', [$this, 'addToDetailsStep']);
        Hook::add('Schema::get::publication', [$this, 'addDataStatementToPublicationSchema']);
        Hook::add('Publication::edit', [$this, 'dataStatementEditingCheck']);
        Hook::add('Template::SubmissionWizard::Section::Review', [$this, 'addToReviewStep']);
        Hook::add('Submission::validateSubmit', [$this, 'validateSubmissionFields']);
        Hook::add('Templates::Preprint::Details', [$this, 'viewDataStatement']);
        Hook::add('Templates::Article::Details', [$this, 'viewDataStatement']);
    }

    public function addDataStatementResourcesToBackend(string $hookName): void
    {
        $request = Application::get()->getRequest();
        $backendPagesToInsert = ['submission', 'workflow', 'authorDashboard', 'reviewer'];

        if (!in_array($request->getRequestedPage(), $backendPagesToInsert)) {
            return;
        }

        $templateMgr = TemplateManager::getManager($request);
        $dataStatementService = new DataStatementService();
        $templateMgr->setConstants($dataStatementService->getConstantsForTemplates());
        $templateMgr->setConstants(['dataStatementTypes' => $dataStatementService->getDataStatementTypes()]);

        $templateMgr->setLocaleKeys([
            'validator.active_url'
        ]);
    }

    public function addDataStatementResources(string $hookName, array $params): bool
    {
        $templateMgr = $params[0];
        $template = $params[1];

        if ($template == 'frontend/pages/preprint.tpl' or $template == 'frontend/pages/article.tpl') {
            $templateMgr->addStyleSheet(
                'dataStatementList',
                $this->plugin->getPluginFullPath() . '/styles/dataStatementList.css',
                ['contexts' => ['frontend']]
            );
        }

        return false;
    }

    public function addToDetailsStep(string $hookName, array $params)
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $templateMgr = $params[0];

        if ($request->getRequestedPage() !== 'submission' || $request->getRequestedOp() === 'saved') {
            return false;
        }

        $submission = $request
            ->getRouter()
            ->getHandler()
            ->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        if (!$submission || !$submission->getData('submissionProgress')) {
            return false;
        }

        $templateMgr->addJavaScript(
            'dataStatementForm',
            $this->plugin->getPluginFullPath() . '/js/ui/components/DataStatementForm.js',
            [
                'priority' => TemplateManager::STYLE_SEQUENCE_LAST,
                'contexts' => ['backend']
            ]
        );

        $templateMgr->addJavaScript(
            'field-controlled-vocab-url',
            $this->plugin->getPluginFullPath() . '/js/ui/components/FieldControlledVocabUrl.js',
            [
                'priority' => TemplateManager::STYLE_SEQUENCE_LAST,
                'contexts' => ['backend']
            ]
        );

        $publication = $submission->getLatestPublication();
        $publicationEndpoint = 'submissions/' . $submission->getId() . '/publications/' . $publication->getId();
        $saveFormUrl = $request->getDispatcher()->url($request, Application::ROUTE_API, $context->getPath(), $publicationEndpoint);
        $dataStatementForm = new DataStatementForm($saveFormUrl, $publication, 'submission');

        $steps = $templateMgr->getState('steps');
        $steps = array_map(function ($step) use ($dataStatementForm) {
            if ($step['id'] === 'details') {
                $step['sections'][] = [
                    'id' => 'dataStatement',
                    'name' => __('plugins.generic.dataverse.dataStatement.title'),
                    'description' => __('plugins.generic.dataverse.dataStatement.description'),
                    'type' => SubmissionHandler::SECTION_TYPE_FORM,
                    'form' => $dataStatementForm->getConfig(),
                ];
            }
            return $step;
        }, $steps);

        $templateMgr->setState(['steps' => $steps]);

        return false;
    }

    public function addDataStatementToPublicationSchema(string $hookName, array $args): bool
    {
        $schema = &$args[0];

        $schema->properties->dataStatementTypes = (object) [
            'type' => 'array',
            'items' => (object) [
                'type' => 'integer',
            ]
        ];

        $schema->properties->dataStatementUrls = (object) [
            'type' => 'array',
            'items' => (object) [
                'type' => 'string',
            ]
        ];

        $schema->properties->dataStatementReason = (object) [
            'type' => 'string',
            'multilingual' => true,
            'validation' => ['nullable']
        ];

        return false;
    }

    public function dataStatementEditingCheck(string $hookName, array $params): bool
    {
        $publication = &$params[0];
        $fields = $params[2];

        if (!isset($fields['dataStatementTypes'])) {
            return false;
        }

        if(!in_array(DataStatementService::DATA_STATEMENT_TYPE_REPO_AVAILABLE, $fields['dataStatementTypes'])) {
            $publication->unsetData('dataStatementUrls');
        }

        if(!in_array(DataStatementService::DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE, $fields['dataStatementTypes'])) {
            $publication->unsetData('dataStatementReason');
        }

        return false;
    }

    public function addToReviewStep(string $hookName, array $params): bool
    {
        $step = $params[0]['step'];
        $templateMgr = $params[1];
        $output = &$params[2];

        if ($step === 'details') {
            $output .= $templateMgr->fetch($this->plugin->getTemplateResource('review/dataStatement.tpl'));
        }

        return false;
    }

    private function inputIsURL(string $input): bool
    {
        $urlPattern = '/^(https?:\/\/)?[a-z0-9\-]+(\.[a-z0-9\-]+)+([\/?#].*)?$/i';
        return preg_match($urlPattern, $input) === 1;
    }

    public function validateSubmissionFields($hookName, $params)
    {
        $errors = &$params[0];
        $submission = $params[1];
        $publication = $submission->getCurrentPublication();

        $dataStatementTypes = $publication->getData('dataStatementTypes');
        $dataStatementUrls = $publication->getData('dataStatementUrls');
        $dataStatementReason = $publication->getData('dataStatementReason');

        if (!$dataStatementTypes) {
            $errors['dataStatement'] = [__('plugins.generic.dataverse.dataStatement.required')];
            return false;
        }

        if (in_array(DataStatementService::DATA_STATEMENT_TYPE_REPO_AVAILABLE, $dataStatementTypes)) {
            if(!$dataStatementUrls) {
                $errors['dataStatementUrls'] = [__('plugins.generic.dataverse.dataStatement.repoAvailable.urls.required')];
            } else {
                foreach ($dataStatementUrls as $url) {
                    if(!$this->inputIsURL($url)) {
                        $errors['dataStatementUrls'] = [__('plugins.generic.dataverse.dataStatement.repoAvailable.urls.urlFormat')];
                        break;
                    }
                }
            }
        }

        if (in_array(DataStatementService::DATA_STATEMENT_TYPE_REPO_AVAILABLE, $dataStatementTypes)
            && !$dataStatementReason
        ) {
            $errors['dataStatementReason'] = [__('plugins.generic.dataverse.dataStatement.publiclyUnavailable.reason.required')];
        }

        return false;
    }

    public function viewDataStatement(string $hookName, array $params): bool
    {
        $templateMgr = &$params[1];
        $output = &$params[2];

        $dataStatementService = new DataStatementService();
        $allDataStatementTypes = $dataStatementService->getDataStatementTypes();
        unset($allDataStatementTypes[DataStatementService::DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED]);

        $templateMgr->assign('dataStatementConsts', $dataStatementService->getConstantsForTemplates());
        $templateMgr->assign('dataStatementMessages', $allDataStatementTypes);

        $output .= $templateMgr->fetch($this->plugin->getTemplateResource('listDataStatement.tpl'));

        return false;
    }
}
