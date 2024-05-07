<?php

namespace APP\plugins\generic\dataverse\controllers\grid;

use PKP\form\Form;
use PKP\form\validation\FormValidator;
use PKP\form\validation\FormValidatorPost;
use PKP\form\validation\FormValidatorCSRF;
use APP\core\Application;
use APP\template\TemplateManager;
use PKP\facades\Locale;
use PKP\db\DAORegistry;
use PKP\core\Core;
use APP\log\event\SubmissionEventLogEntry;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use APP\plugins\generic\dataverse\classes\facades\Repo;

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
        $locale = Locale::getLocale();

        $dataverseClient = new DataverseClient();
        $dataverseCollection = $dataverseClient->getDataverseCollectionActions()->get();

        $configuration = DAORegistry::getDAO('DataverseConfigurationDAO')->get($contextId);
        $termsOfUse = $configuration->getLocalizedData('termsOfUse', $locale);

        return [
            'termsOfUseURL' => $termsOfUse,
            'dataverseName' => $dataverseCollection->getName()
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
            'submissionId',
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

        $draftDatasetFile = Repo::draftDatasetFile()->newDataObject();
        $draftDatasetFile->setData('submissionId', $this->getData('submissionId'));
        $draftDatasetFile->setData('userId', $user->getId());
        $draftDatasetFile->setData('fileId', $temporaryFile->getId());
        $draftDatasetFile->setData('fileName', $temporaryFile->getOriginalFileName());
        Repo::draftDatasetFile()->add($draftDatasetFile);

        $submission = Repo::submission()->get($this->getData('submissionId'));

        $researchDataLog = Repo::eventLog()->newDataObject([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submission->getId(),
            'eventType' => SubmissionEventLogEntry::SUBMISSION_LOG_FILE_UPLOAD,
            'message' => __('plugins.generic.dataverse.log.researchDataFileAdded', ['filename' => $draftDatasetFile->getData('fileName')]),
            'isTranslated' => true,
            'dateLogged' => Core::getCurrentDate(),
        ]);
        Repo::eventLog()->add($researchDataLog);

        parent::execute(...$functionArgs);
        return $draftDatasetFile->getId();
    }
}
