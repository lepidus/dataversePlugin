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
		$this->readUserVars(array('temporaryFileId', 'submissionId'));
		return parent::readInputData();
	}

	function execute(...$functionArgs) {
		$userId = Application::get()->getRequest()->getUser()->getId();

		// Fetch the temporary file storing the uploaded library file
		$temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO'); /* @var $temporaryFileDao TemporaryFileDAO */
		$temporaryFile = $temporaryFileDao->getTemporaryFile(
			$this->getData('temporaryFileId'),
			$userId
		);
		$libraryFileDao = DAORegistry::getDAO('LibraryFileDAO'); /* @var $libraryFileDao LibraryFileDAO */
		$libraryFileManager = new LibraryFileManager($this->contextId);
		
		// Convert the temporary file to a library file and store
		$libraryFile =& $libraryFileManager->copyFromTemporaryFile($temporaryFile, $this->getData('fileType'));
		assert(isset($libraryFile));
		$libraryFile->setContextId($this->contextId);
		$libraryFile->setName($this->getData('libraryFileName'), null); // Localized
		$libraryFile->setType($this->getData('fileType'));
		$libraryFile->setSubmissionId($this->getData('submissionId'));

		$fileId = $libraryFileDao->insertObject($libraryFile);

		// Clean up the temporary file
		import('lib.pkp.classes.file.TemporaryFileManager');
		$temporaryFileManager = new TemporaryFileManager();
		$temporaryFileManager->deleteById($this->getData('temporaryFileId'), $userId);

		parent::execute(...$functionArgs);

		return $fileId;
	}
}
