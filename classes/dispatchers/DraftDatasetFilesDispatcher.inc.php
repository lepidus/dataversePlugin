<?php

import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');
import('plugins.generic.dataverse.classes.DraftDatasetFilesValidator');

class DraftDatasetFilesDispatcher extends DataverseDispatcher
{
    public function __construct(Plugin $plugin)
    {
        HookRegistry::register('submissionsubmitstep2form::display', array($this, 'addDraftDatasetFileContainer'));
        HookRegistry::register('submissionsubmitstep2form::validate', array($this, 'validateDraftDatasetFiles'));

        parent::__construct($plugin);
    }

    public function addDraftDatasetFileContainer(string $hookName, array $params): ?string
    {
        $form = $params[0];
        $output =& $params[1];

        $request = PKPApplication::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);

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

    public function validateDraftDatasetFiles(string $hookName, array $params): void
    {
        $form =& $params[0];
        $submission = $form->submission;

        $draftDatasetFileDAO = DAORegistry::getDAO('DraftDatasetFileDAO');
        $draftDatasetFiles = $draftDatasetFileDAO->getBySubmissionId($submission->getId());

        if (empty($draftDatasetFiles)) {
            return;
        }

        $this->validateDataverseTermsOfUse($form);
        $this->validateGalleyContainsResearchData($form);
    }

    private function validateDataverseTermsOfUse(SubmissionSubmitStep2Form $form): void
    {
        $form->readUserVars(['termsOfUse']);

        if (!$form->getData('termsOfUse')) {
            $form->addError('dataverseStep2ValidationError', __('plugins.generic.dataverse.termsOfUse.error'));
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

        $validator = new DraftDatasetFilesValidator();
        if ($validator->galleyContainsResearchData($galleyFiles, $datasetFiles)) {
            $form->addError('dataverseStep2ValidationError', __("plugins.generic.dataverse.notification.galleyContainsResearchData"));
            $form->addErrorField('dataverseStep2ValidationError');
        }
    }
}
