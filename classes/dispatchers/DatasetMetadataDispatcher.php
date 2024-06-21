<?php

namespace APP\plugins\generic\dataverse\classes\dispatchers;

use PKP\plugins\Hook;
use APP\core\Application;
use APP\pages\submission\SubmissionHandler;
use APP\plugins\generic\dataverse\classes\dispatchers\DataverseDispatcher;
use APP\plugins\generic\dataverse\classes\DataverseMetadata;
use APP\plugins\generic\dataverse\classes\entities\Dataset;
use APP\plugins\generic\dataverse\classes\components\forms\DatasetMetadataForm;
use APP\plugins\generic\dataverse\classes\services\DataStatementService;

class DatasetMetadataDispatcher extends DataverseDispatcher
{
    protected function registerHooks(): void
    {
        Hook::add('TemplateManager::display', [$this, 'addToEditorsStep']);
        Hook::add('Template::SubmissionWizard::Section::Review', [$this, 'addToReviewStep']);
        Hook::add('Submission::validateSubmit', [$this, 'validateSubmissionFields']);
    }

    public function addToEditorsStep(string $hookName, array $params)
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

        $submissionApiUrl = $request->getDispatcher()->url($request, Application::ROUTE_API, $context->getPath(), 'submissions/' . $submission->getId());
        $dataset = new Dataset();
        $dataset->setData('subject', $submission->getData('datasetSubject'));
        $dataset->setData('license', $submission->getData('datasetLicense'));
        $datasetMetadataForm = new DatasetMetadataForm($submissionApiUrl, 'POST', $dataset, 'submission');

        $steps = $templateMgr->getState('steps');
        $steps = array_map(function ($step) use ($datasetMetadataForm) {
            if ($step['id'] === 'editors') {
                $step['sections'][] = [
                    'id' => 'datasetMetadata',
                    'name' => __('plugins.generic.dataverse.datasetMetadata'),
                    'description' => __('plugins.generic.dataverse.datasetMetadata.description'),
                    'type' => SubmissionHandler::SECTION_TYPE_FORM,
                    'form' => $datasetMetadataForm->getConfig()
                ];
            }
            return $step;
        }, $steps);

        $templateMgr->setState(['steps' => $steps]);

        return false;
    }

    public function addToReviewStep(string $hookName, array $params): bool
    {
        $step = $params[0]['step'];
        $templateMgr = $params[1];
        $output = &$params[2];

        if ($step === 'editors') {
            $output .= $templateMgr->fetch($this->plugin->getTemplateResource('review/datasetMetadata.tpl'));
        }

        return false;
    }

    public function validateSubmissionFields(string $hookName, array $params)
    {
        $errors = &$params[0];
        $submission = $params[1];
        $publication = $submission->getCurrentPublication();

        $dataStatementTypes = $publication->getData('dataStatementTypes');

        if (
            !empty($dataStatementTypes)
            && in_array(DataStatementService::DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED, $dataStatementTypes)
        ) {
            if (!$submission->getData('datasetSubject')) {
                $errors['datasetSubject'] = [__('plugins.generic.dataverse.error.datasetSubject.required')];
            }
        }

        return false;
    }
}
