<?php

import('lib.pkp.classes.form.Form');
import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');

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
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('submissionId', $this->submissionId);
		return parent::fetch($request);
	}

	function readInputData(): void
	{
		$this->readUserVars(array('publishData', 'galleyItems'));
	}

	function execute(...$functionArgs)
	{
		if ($this->getData('publishData')) 
        {
            $galleysId = $this->getData('galleyItems');
			$articleGalleyDao = DAORegistry::getDAO('ArticleGalleyDAO');
            foreach ($galleysId as $galleyId) {
                $galley = $articleGalleyDao->getById($galleyId);
                $submissionFile = Services::get('submissionFile')->get($galley->getData('submissionFileId'));
                $newSubmissionFile = Services::get('submissionFile')->edit(
                    $submissionFile,
                    ['publishData' => true],
                    Application::get()->getRequest()
                );
            }
        }
		parent::execute(...$functionArgs);
	}
}
