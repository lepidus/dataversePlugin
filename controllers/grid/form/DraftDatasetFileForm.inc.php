<?php

import('lib.pkp.classes.form.Form');
import('plugins.generic.dataverse.classes.draftDatasetFile.DraftDatasetFileDAO');

class DraftDatasetFileForm extends Form
{
    private $submissionId;

    private $publicationId;

    public function __construct($template, $submissionId, $publicationId)
    {
        parent::__construct($template);

        $this->submissionId = $submissionId;
        $this->publicationId = $publicationId;

        $this->addCheck(new FormValidator($this, 'temporaryFileId', 'required', 'plugins.generic.dataverse.researchDataFile.error'));
        $this->addCheck(new FormValidator($this, 'termsOfUse', 'required', 'plugins.generic.dataverse.termsOfUse.error'));

        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    public function getSubmissionId()
    {
        return $this->submissionId;
    }

    public function getPublicationId()
    {
        return $this->publicationId;
    }

    public function getRequestArgs(): array
    {
        return [
            'submissionId' => $this->getSubmissionId(),
            'publicationId' => $this->getPublicationId()
        ];
    }

    private function getTermsOfUseArgs(): array
    {
        $contextId = Application::get()->getRequest()->getContext()->getId();
        $locale = AppLocale::getLocale();

        import('plugins.generic.dataverse.dataverseAPI.DataverseClient');
        $dataverseClient = new DataverseClient();
        $dataverseCollection = $dataverseClient->getDataverseCollectionActions()->get();

        $configuration = DAORegistry::getDAO('DataverseConfigurationDAO')->get($contextId);
        $termsOfUse = $configuration->getLocalizedData('termsOfUse', $locale);

        import('plugins.generic.dataverse.settings.DataverseSettingsForm');

        return [
            'termsOfUseURL' => DataverseSettingsForm::isHttpUrl($termsOfUse) ? htmlspecialchars($termsOfUse, ENT_QUOTES) : '',
            'dataverseName' => htmlspecialchars($dataverseCollection->getName(), ENT_QUOTES)
        ];
    }

    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('submissionId', $this->getSubmissionId());
        $templateMgr->assign('requestArgs', $this->getRequestArgs());
        $templateMgr->assign('termsOfUseArgs', $this->getTermsOfUseArgs());
        return parent::fetch($request, $template, $display);
    }

    public function readInputData()
    {
        $this->readUserVars([
            'temporaryFileId',
            'termsOfUse'
        ]);
    }

    public function execute(...$functionArgs)
    {
        $request = Application::get()->getRequest();
        $user = $request->getUser();

        $temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO');
        $temporaryFile = $temporaryFileDao->getTemporaryFile(
            $this->getData('temporaryFileId'),
            $user->getId()
        );

        if (!$temporaryFile) {
            throw new DraftDatasetFileStorageException('The research data upload has expired.');
        }

        $draftDatasetFileDAO = new DraftDatasetFileDAO();
        $draftDatasetFile = $draftDatasetFileDAO->newDataObject();
        $draftDatasetFile->setSubmissionId($this->getSubmissionId());
        $draftDatasetFile->setData('userId', $user->getId());
        $draftDatasetFile->setData('fileId', $temporaryFile->getId());
        $draftDatasetFile->setData('fileName', $temporaryFile->getOriginalFileName());
        $draftDatasetFileDAO->attachUpload($draftDatasetFile, $temporaryFile);

        $submission = Services::get('submission')->get($this->getSubmissionId());

        import('lib.pkp.classes.log.SubmissionLog');
        import('lib.pkp.classes.log.SubmissionFileEventLogEntry');
        \SubmissionLog::logEvent(
            $request,
            $submission,
            SUBMISSION_LOG_FILE_UPLOAD,
            'plugins.generic.dataverse.log.researchDataFileAdded',
            ['filename' => $draftDatasetFile->getData('fileName')]
        );

        parent::execute(...$functionArgs);
        return $draftDatasetFile->getId();
    }
}
