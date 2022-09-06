<?php

import('lib.pkp.classes.form.Form');

class AddDatasetFileForm extends Form {

	private $plugin;
	private $submissionId;
    private $publicationId;
	
	public function __construct(Plugin $plugin, int $submissionId, int $publicationId) {
		$this->plugin = $plugin;
		$this->submissionId = $submissionId;
        $this->publicationId = $publicationId;

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

	function readInputData(): void
	{
	}

	function execute(...$functionArgs)
	{
		parent::execute(...$functionArgs);
	}
}
