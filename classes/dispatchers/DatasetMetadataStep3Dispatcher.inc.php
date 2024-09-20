<?php

import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');
import('plugins.generic.dataverse.classes.DataverseMetadata');

class DatasetMetadataStep3Dispatcher extends DataverseDispatcher
{
    protected function registerHooks(): void
    {
        HookRegistry::register('Templates::Submission::SubmissionMetadataForm::AdditionalMetadata', array($this, 'addDatasetMetadataFields'));
        HookRegistry::register('submissionsubmitstep3form::validate', array($this, 'readDatasetMetadataFields'));
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
        foreach ($licenses as $license) {
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

        if (is_null($subject)) {
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
