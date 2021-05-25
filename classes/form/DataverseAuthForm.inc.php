<?php

import('lib.pkp.classes.form.Form');
import('plugins.generic.dataversePlugin.classes.DataverseRepository');
import('plugins.generic.dataversePlugin.classes.DataverseDAO');

class DataverseAuthForm extends Form {

	private $plugin;

	private $contextId;

	function DataverseAuthForm($plugin, $contextId) {
		$this->plugin = $plugin;
		$this->contextId = $contextId;

		parent::__construct($plugin->getTemplateResource('dataverseAuthForm.tpl'));
		$this->addCheck(new FormValidatorUrl($this, 'dataverseServer', FORM_VALIDATOR_REQUIRED_VALUE, 'plugins.generic.dataverse.settings.dataverseServerRequired'));
		$this->addCheck(new FormValidator($this, 'apiToken', FORM_VALIDATOR_REQUIRED_VALUE, 'plugins.generic.dataverse.settings.tokenRequired'));
		$this->addCheck(new FormValidatorCustom($this, '', FORM_VALIDATOR_REQUIRED_VALUE, 'plugins.generic.dataverse.settings.dataverseServerNotValid', array($this, 'validateCredentials')));
		$this->addCheck(new FormValidatorPost($this));
	}

	function initData() {
		$plugin = $this->plugin;
		$this->setData('dataverseServer', $plugin->getSetting($this->contextId, 'dataverseServer'));		 
		$this->setData('apiToken', $plugin->getSetting($this->contextId, 'apiToken'));
	}

	function readInputData() {
		$this->readUserVars(array('dataverseServer', 'apiToken'));
		$request = PKPApplication::getRequest();
		$this->setData('dataverseServer', $this->normalizeURI($this->getData('dataverseServer')));
	}

	private function normalizeURI($uri) {
		return preg_replace("/\/+$/", '', $uri);
	}

	function fetch($request, $template = NULL, $display = false) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('pluginName', $this->plugin->getName());
		return parent::fetch($request);
	}

	function execute(...$functionArgs) {
		$plugin = $this->plugin;
		$plugin->updateSetting($this->contextId, 'dataverseServer', $this->getData('dataverseServer'), 'string');
		$plugin->updateSetting($this->contextId, 'apiToken', $this->getData('apiToken'), 'string');
		$plugin->updateSetting($this->contextId, 'apiVersion', $this->getData('apiVersion'), 'string');
		parent::execute(...$functionArgs);
	}

	function validateCredentials() {
		$repository = new DataverseRepository($this->getData("apiToken"), $this->getData("dataverseServer"));
		$connectionSuccessful = $repository->checkConnectionWithDataverse();

		if ($connectionSuccessful) {
			$dataverseDAO = new DataverseDAO();
			$dataverseDAO->insertCredentialsOnDatabase($this->contextId, $this->getData("dataverseServer"), $this->getData("apiToken"));
		}

		return $connectionSuccessful;
	}
}
