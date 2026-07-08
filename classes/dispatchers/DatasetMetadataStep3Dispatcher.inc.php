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
            $datasetSubjectLabels = array_column($dataverseSubjectVocab, 'label');
            $datasetSubjectValues = array_column($dataverseSubjectVocab, 'value');

            $availableLicenses = $dataverseMetadata->getDataverseLicenses();
            $selectedLicense = $submission->getData('datasetLicense') ?? $dataverseMetadata->getDefaultLicense();

            $availableLanguages = $this->getAvailableLanguages();
            $selectedLanguage = $submission->getData('datasetLanguage') ?? \Locale::getDisplayLanguage($submission->getLocale(), 'en');

            $availableRelationTypes = DataverseMetadata::getDataverseRelationTypes();
            $selectedRelationType = $submission->getData('datasetRelationType') ?? DataverseMetadata::DEFAULT_RELATION_TYPE;

            $templateMgr->assign([
                'selectedLanguage' => $selectedLanguage,
                'availableLanguages' => $availableLanguages,
                'subjectId' => array_search($submission->getData('datasetSubject'), $datasetSubjectValues),
                'dataverseSubjectVocab' => $datasetSubjectLabels,
                'selectedLicense' => $selectedLicense,
                'availableLicenses' => $this->mapValuesForStep3Display($availableLicenses, 'name', 'name'),
                'selectedRelationType' => $selectedRelationType,
                'availableRelationTypes' => $this->mapValuesForStep3Display($availableRelationTypes, 'label', 'value')
            ]);

            $output .= $templateMgr->fetch($this->plugin->getTemplateResource('datasetMetadataStep3.tpl'));
        }
    }

    private function mapValuesForStep3Display(array $licenses, string $fieldForLabel, string $fieldForValue): array
    {
        $mappedLicenses = [];
        foreach ($licenses as $license) {
            $mappedLicenses[$license[$fieldForValue]] = $license[$fieldForLabel];
        }
        return $mappedLicenses;
    }

    private function getAvailableLanguages(): array
    {
        $context = Application::get()->getRequest()->getContext();
        $availableLanguages = [];

        foreach ($context->getSupportedSubmissionLocales() as $locale) {
            $languageName = \Locale::getDisplayLanguage($locale, 'en');
            $availableLanguages[$languageName] = $languageName;
        }

        return $availableLanguages;
    }

    public function readDatasetMetadataFields($hookName, $args): bool
    {
        $form = &$args[0];
        $submission = &$form->submission;

        $form->readUserVars(['datasetLanguage', 'datasetSubject', 'datasetLicense', 'datasetRelationType']);
        $language = $form->getData('datasetLanguage');
        $subject = $form->getData('datasetSubject');
        $license = $form->getData('datasetLicense');
        $relationType = $form->getData('datasetRelationType');

        if (is_null($subject)) {
            return false;
        }

        $dataverseMetadata = new DataverseMetadata();
        $dataverseSubjectVocab = $dataverseMetadata->getDataverseSubjects();
        $datasetSubjectValues = array_column($dataverseSubjectVocab, 'value');

        $newSubmission = Services::get('submission')->edit(
            $submission,
            [
                'datasetLanguage' => $language,
                'datasetSubject' => $datasetSubjectValues[$subject],
                'datasetLicense' => $license,
                'datasetRelationType' => $relationType
            ],
            Application::get()->getRequest()
        );
        $form->submission = $newSubmission;

        return false;
    }
}
