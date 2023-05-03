<?php

import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');

class DraftDatasetFilesDispatcher extends DataverseDispatcher
{
    protected function registerHooks(): void
    {
        HookRegistry::register('submissionsubmitstep2form::display', array($this, 'addDraftDatasetFileContainer'));
        HookRegistry::register('submissionsubmitstep2form::validate', array($this, 'addStep2Validation'));
    }

    public function addDraftDatasetFileContainer(string $hookName, array $params): ?string
    {
        $form = $params[0];
        $output =& $params[1];
        $publication = $form->submission->getCurrentPublication();

        $request = PKPApplication::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);

        $requestArgs = $templateMgr->get_template_vars('requestArgs');
        if (empty($requestArgs)) {
            $requestArgs = [
                'submissionId' => $form->submission->getId(),
                'publicationId' => $publication->getId(),
            ];
            $templateMgr->assign('requestArgs', $requestArgs);
        }

        if ($publication->getData('researchDataState') != RESEARCH_DATA_SUBMISSION_DEPOSIT) {
            return $output;
        }

        $templateOutput = $templateMgr->fetch($form->_template);
        $pattern = '/<div[^>]+class="section formButtons form_buttons[^>]+>/';
        if (preg_match($pattern, $templateOutput, $matches, PREG_OFFSET_CAPTURE)) {
            $offset = $matches[0][1];
            $output = substr($templateOutput, 0, $offset);
            $output .= $templateMgr->fetch($this->plugin->getTemplateResource('draftDatasetFile.tpl'));
            $output .= substr($templateOutput, $offset);
        }

        return $output;
    }

    public function addStep2Validation(string $hookName, array $params): void
    {
        $form =& $params[0];

        $this->validateResearchDataFileRequired($form);
        $this->validateGalleyContainsResearchData($form);
    }

    private function validateResearchDataFileRequired(SubmissionSubmitStep2Form $form): void
    {
        $publication = $form->submission->getCurrentPublication();

        if ($publication->getData('researchDataState') != RESEARCH_DATA_SUBMISSION_DEPOSIT) {
            return;
        }

        $draftDatasetFileDAO = DAORegistry::getDAO('DraftDatasetFileDAO');
        if (empty($draftDatasetFileDAO->getBySubmissionId($form->submission->getId()))) {
            $form->addError('dataverseStep2ValidationError', __("plugins.generic.dataverse.researchDataFile.error"));
            $form->addErrorField('dataverseStep2ValidationError');
        }
    }

    private function validateGalleyContainsResearchData(SubmissionSubmitStep2Form $form): void
    {
        $galleys = $form->submission->getGalleys();

        if (empty($galleys)) {
            return;
        }

        $galleyFiles = array_map(function (ArticleGalley $galley) {
            return Services::get('submissionFile')->get($galley->getFileId());
        }, $galleys);

        $draftDatasetFileDAO = DAORegistry::getDAO('DraftDatasetFileDAO');
        $draftDatasetFiles = $draftDatasetFileDAO->getBySubmissionId($form->submission->getId());

        import('lib.pkp.classes.file.TemporaryFileManager');
        $datasetFiles = array_map(function (DraftDatasetFile $draftFile) {
            $temporaryFileManager = new TemporaryFileManager();
            return $temporaryFileManager->getFile(
                $draftFile->getData('fileId'),
                $draftFile->getData('userId')
            );
        }, $draftDatasetFiles);

        import('plugins.generic.dataverse.classes.DraftDatasetFilesValidator');
        $validator = new DraftDatasetFilesValidator();
        if ($validator->galleyContainsResearchData($galleyFiles, $datasetFiles)) {
            $form->addError('dataverseStep2ValidationError', __("plugins.generic.dataverse.notification.galleyContainsResearchData"));
            $form->addErrorField('dataverseStep2ValidationError');
        }
    }
}
