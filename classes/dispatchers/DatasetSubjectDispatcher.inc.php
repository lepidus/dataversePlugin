<?php

import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');

class DatasetSubjectDispatcher extends DataverseDispatcher
{
    protected function registerHooks(): void
    {
        HookRegistry::register('Templates::Submission::SubmissionMetadataForm::AdditionalMetadata', array($this, 'addSubjectField'));
        HookRegistry::register('submissionsubmitstep3form::validate', array($this, 'readSubjectField'));
    }

    public function addSubjectField($hookName, $args): void
    {
        $templateMgr =& $args[1];
        $output =& $args[2];

        $submissionId = $templateMgr->get_template_vars('submissionId');
        $submission = Services::get('submission')->get($submissionId);

        $draftDatasetFileDAO = DAORegistry::getDAO('DraftDatasetFileDAO');
        $draftDatasetFiles = $draftDatasetFileDAO->getBySubmissionId($submissionId);

        if (!empty($draftDatasetFiles)) {
            import('plugins.generic.dataverse.classes.DataverseMetadata');
            $dataverseSubjectVocab = DataverseMetadata::getDataverseSubjects();

            $datasetSubjectLabels = array_column($dataverseSubjectVocab, 'label');
            $datasetSubjectValues = array_column($dataverseSubjectVocab, 'value');

            $templateMgr->assign([
                'dataverseSubjectVocab' => $datasetSubjectLabels,
                'subjectId' => array_search($submission->getData('datasetSubject'), $datasetSubjectValues)
            ]);

            $output .= $templateMgr->fetch($this->plugin->getTemplateResource('subjectField.tpl'));
        }
    }

    public function readSubjectField($hookName, $args): bool
    {
        $form =& $args[0];
        $submission =& $form->submission;

        $form->readUserVars(array('datasetSubject'));
        $subject = $form->getData('datasetSubject');

        if(empty($subject)) {
            return false;
        }

        import('plugins.generic.dataverse.classes.DataverseMetadata');
        $dataverseSubjectVocab = DataverseMetadata::getDataverseSubjects();
        $datasetSubjectValues = array_column($dataverseSubjectVocab, 'value');

        Services::get('submission')->edit(
            $submission,
            ['datasetSubject' => $datasetSubjectValues[$subject]],
            Application::get()->getRequest()
        );

        return false;
    }
}
