<?php

import('lib.pkp.classes.form.Form');
import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');

use \PKP\components\forms\FieldUpload;

class UploadDatasetForm extends Form {

	private $plugin;
	private $submissionId;
	
	public function __construct(Plugin $plugin, int $submissionId) {
		$this->plugin = $plugin;
		$this->submissionId = $submissionId;

		parent::__construct($this->plugin->getTemplateResource('sendDatasetForm.tpl'));

		$this->addCheck(new FormValidator($this, 'publishData', 'required', 'editor.submissions.galleyLabelRequired'));
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	function fetch($request, $template = null, $display = false)
	{
		AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_SUBMISSION,
			LOCALE_COMPONENT_PKP_USER,
			LOCALE_COMPONENT_PKP_EDITOR,
			LOCALE_COMPONENT_APP_EDITOR
		);
		
		$templateMgr = TemplateManager::getManager($request);
		$userId = Application::get()->getRequest()->getUser()->getId();
		$contextId = $request->getContext()->getId();

		$roleDao = DaoRegistry::getDao('RoleDAO');
		$hasRole = $roleDao->userHasRole($contextId, $userId, ROLE_ID_MANAGER);

		$submission = Services::get('submission')->get($this->submissionId);
        $publication = $submission->getCurrentPublication();

		$articleGalleyDao = DAORegistry::getDAO('ArticleGalleyDAO');
		$articleGalley = $articleGalleyDao->newDataObject();
		$articleGalley->setData('publicationId', $publication->getId());
		$articleGalley->setLabel('Dataset');
		$articleGalley->setLocale($submission->getLocale());
		$articleGalley->setData('urlPath', '');
		$articleGalley->setData('urlRemote', '');
		$articleGalley->setData('dataverseGalley', true);

		// Insert new galley into the db
		$galleyId = $articleGalleyDao->insertObject($articleGalley);

		import('lib.pkp.controllers.api.file.linkAction.AddFileLinkAction');
		import('lib.pkp.classes.submission.SubmissionFile'); // Constants
		$uploadDatasetFileAction = new AddFileLinkAction(
			$request, $this->submissionId, WORKFLOW_STAGE_ID_PRODUCTION,
			array(ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN, ROLE_ID_SUB_EDITOR, ROLE_ID_AUTHOR, ROLE_ID_REVIEWER, ROLE_ID_ASSISTANT, ROLE_ID_READER, ROLE_ID_SUBSCRIPTION_MANAGER),
			SUBMISSION_FILE_PROOF, ASSOC_TYPE_REPRESENTATION, $galleyId
		);

		$templateMgr->assign('submissionId', $this->submissionId);
		$templateMgr->assign('uploadDatasetFileAction', $uploadDatasetFileAction);
		return parent::fetch($request);
	}

	function readInputData(): void
	{
		$this->readUserVars(array('publishData', 'galleyItems', 'submissionId'));
	}

	function execute(...$functionArgs)
	{
		if ($this->getData('publishData')) 
        {
			$libraryFileDao = DAORegistry::getDAO('LibraryFileDAO'); 
			$submissionId = $this->getData('submissionId');
        }
		parent::execute(...$functionArgs);
	}
}
