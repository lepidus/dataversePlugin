<?php

import('lib.pkp.classes.form.Form');

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
        try {
            $context = Application::get()->getRequest()->getContext();
            $locale = AppLocale::getLocale();

            import('plugins.generic.dataverse.classes.dataverseAPI.clients.NativeAPIClient');
            $dvAPIClient = new NativeAPIClient($context->getId());

            import('plugins.generic.dataverse.classes.dataverseAPI.services.DataAPIService');
            $dvDataService = new DataAPIService($dvAPIClient);

            $termsOfUse = $dvAPIClient->getCredentials()->getLocalizedData('termsOfUse', $locale);
            $dvCollectionName = $dvDataService->getDataverseCollectionName();

            return [
                'termsOfUseURL' => $termsOfUse,
                'dataverseName' => $dvCollectionName
            ];
        } catch (Exception $e) {
            error_log($e->getMessage());
        }
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
            'submissionId',
            'termsOfUse'
        ]);
    }

    public function execute(...$functionArgs)
    {
        $userId = Application::get()->getRequest()->getUser()->getId();

        $temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO');
        $temporaryFile = $temporaryFileDao->getTemporaryFile(
            $this->getData('temporaryFileId'),
            $userId
        );

        import('plugins.generic.dataverse.classes.file.DraftDatasetFileDAO');
        $draftDatasetFileDAO = new DraftDatasetFileDAO();
        $draftDatasetFile = $draftDatasetFileDAO->newDataObject();
        $draftDatasetFile->setData('submissionId', $this->getData('submissionId'));
        $draftDatasetFile->setData('userId', $userId);
        $draftDatasetFile->setData('fileId', $temporaryFile->getId());
        $draftDatasetFile->setData('fileName', $temporaryFile->getOriginalFileName());

        $draftDatasetFileId = $draftDatasetFileDAO->insertObject($draftDatasetFile);

        parent::execute(...$functionArgs);
        return $draftDatasetFileId;
    }
}
