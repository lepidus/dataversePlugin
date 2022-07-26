<?php

import('lib.pkp.classes.form.Form');

class UploadDatasetForm extends Form {

	private $plugin;
	
	public function __construct(Plugin $plugin) {
		$this->plugin = $plugin;

		parent::__construct($this->plugin->getTemplateResource('sendDatasetForm.tpl'));

		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}
}
