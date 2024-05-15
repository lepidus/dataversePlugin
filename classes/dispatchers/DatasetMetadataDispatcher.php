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
        // HookRegistry::register('Templates::Submission::SubmissionMetadataForm::AdditionalMetadata', array($this, 'addDatasetMetadataFields'));
        // HookRegistry::register('submissionsubmitstep3form::validate', array($this, 'readDatasetMetadataFields'));
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

        if (in_array(DataStatementService::DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED, $dataStatementTypes)) {
            if (!$submission->getData('datasetSubject')) {
                $errors['datasetSubject'] = [__('plugins.generic.dataverse.error.datasetSubjectRequired')];
            }
        }

        return false;
    }

    public function addDatasetMetadataFields($hookName, $args): void
    {
        $templateMgr = &$args[1];
        $output = &$args[2];

        $submissionId = $templateMgr->get_template_vars('submissionId');
        $submission = Services::get('submission')->get($submissionId);

        $draftDatasetFileDAO = DAORegistry::getDAO('DraftDatasetFileDAO');
        $draftDatasetFiles = $draftDatasetFileDAO->getBySubmissionId($submissionId);

        if (!empty($draftDatasetFiles)) {
            $dataverseMetadata = new DataverseMetadata();
            $dataverseSubjectVocab = $dataverseMetadata->getDataverseSubjects();
            $availableLicenses = $dataverseMetadata->getDataverseLicenses();

            $datasetSubjectLabels = array_column($dataverseSubjectVocab, 'label');
            $datasetSubjectValues = array_column($dataverseSubjectVocab, 'value');

            $selectedLicense = $submission->getData('datasetLicense') ?? $dataverseMetadata->getDefaultLicense();

            $templateMgr->assign([
                'dataverseSubjectVocab' => $datasetSubjectLabels,
                'availableLicenses' => $this->mapLicensesForStep3Display($availableLicenses),
                'subjectId' => array_search($submission->getData('datasetSubject'), $datasetSubjectValues),
                'selectedLicense' => $selectedLicense
            ]);

            $output .= $templateMgr->fetch($this->plugin->getTemplateResource('datasetMetadataStep3.tpl'));
        }
    }

    private function mapLicensesForStep3Display(array $licenses): array
    {
        $mappedLicenses = [];
        foreach($licenses as $license) {
            $mappedLicenses[$license['name']] = $license['name'];
        }
        return $mappedLicenses;
    }

    public function readDatasetMetadataFields($hookName, $args): bool
    {
        $form = &$args[0];
        $submission = &$form->submission;

        $form->readUserVars(array('datasetSubject', 'datasetLicense'));
        $subject = $form->getData('datasetSubject');
        $license = $form->getData('datasetLicense');

        if(is_null($subject)) {
            return false;
        }

        $dataverseMetadata = new DataverseMetadata();
        $dataverseSubjectVocab = $dataverseMetadata->getDataverseSubjects();
        $datasetSubjectValues = array_column($dataverseSubjectVocab, 'value');

        Services::get('submission')->edit(
            $submission,
            [
                'datasetSubject' => $datasetSubjectValues[$subject],
                'datasetLicense' => $license
            ],
            Application::get()->getRequest()
        );

        return false;
    }
}
