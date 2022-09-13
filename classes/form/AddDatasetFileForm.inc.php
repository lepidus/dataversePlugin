<?php

import('lib.pkp.classes.form.Form');
import('classes.file.LibraryFileManager');

class AddDatasetFileForm extends Form {

	private $plugin;
	private $submissionId;
    private $publicationId;
	private $contextId;
	
	public function __construct(Plugin $plugin, int $submissionId, int $publicationId, int $contextId) {
		$this->plugin = $plugin;
		$this->submissionId = $submissionId;
        $this->publicationId = $publicationId;
		$this->contextId = $contextId;

		parent::__construct($this->plugin->getTemplateResource('addDatasetFileForm.tpl'));
	}

    function initData()
	{
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('submissionId', $this->submissionId);
        $templateMgr->assign('publicationId', $this->publicationId);
		return parent::fetch($request);
	}


	function fetch($request, $template = null, $display = false)
	{
		return parent::fetch($request);
	}

	function readInputData()
	{
		$this->readUserVars(array('label', 'temporaryFileId', 'submissionId'));
		return parent::readInputData();
	}

	function execute(...$functionArgs) {
		$userId = Application::get()->getRequest()->getUser()->getId();

		$temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO');
		$temporaryFile = $temporaryFileDao->getTemporaryFile(
			$this->getData('temporaryFileId'),
			$userId
		);
		$libraryFileDao = DAORegistry::getDAO('LibraryFileDAO'); 
		$libraryFileManager = new LibraryFileManager($this->contextId);
		
		$libraryFile =& $libraryFileManager->copyFromTemporaryFile($temporaryFile, $this->getData('fileType'));
		assert(isset($libraryFile));
		$libraryFile->setContextId($this->contextId);
		$locale = AppLocale::getLocale();
		$libraryFile->setName($this->getData('label'), $locale); 
		$libraryFile->setType($this->getData('fileType'));
		$libraryFile->setSubmissionId($this->getData('submissionId'));
		$fileId = $libraryFileDao->insertObject($libraryFile);

		import('lib.pkp.classes.file.TemporaryFileManager');
		$temporaryFileManager = new TemporaryFileManager();
		$temporaryFileManager->deleteById($this->getData('temporaryFileId'), $userId);

		parent::execute(...$functionArgs);

		return $fileId;
	}
}
