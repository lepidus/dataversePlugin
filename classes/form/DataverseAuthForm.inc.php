<?php

import('lib.pkp.classes.form.Form');
import('plugins.generic.dataverse.classes.api.DataverseClient');
import('plugins.generic.dataverse.classes.DataverseDAO');

class DataverseAuthForm extends Form {

	private Plugin $plugin;
	private int $contextId;

	function DataverseAuthForm(Plugin $plugin, int $contextId)
	{
		$this->plugin = $plugin;
		$this->contextId = $contextId;

		parent::__construct($plugin->getTemplateResource('dataverseAuthForm.tpl'));
		$this->addCheck(new FormValidatorUrl($this, 'dataverseServer', FORM_VALIDATOR_REQUIRED_VALUE, 'plugins.generic.dataverse.settings.dataverseServerRequired'));
		$this->addCheck(new FormValidatorUrl($this, 'dataverse', FORM_VALIDATOR_REQUIRED_VALUE, 'plugins.generic.dataverse.settings.dataverse'));
		$this->addCheck(new FormValidator($this, 'apiToken', FORM_VALIDATOR_REQUIRED_VALUE, 'plugins.generic.dataverse.settings.tokenRequired'));
		$this->addCheck(new FormValidatorCustom($this, '', FORM_VALIDATOR_REQUIRED_VALUE, 'plugins.generic.dataverse.settings.dataverseServerNotValid', array($this, 'validateCredentials')));
		$this->addCheck(new FormValidatorPost($this));
	}

	function initData(): void
	{
		$plugin = $this->plugin;
		$this->setData('dataverseServer', $plugin->getSetting($this->contextId, 'dataverseServer'));
		$this->setData('dataverse', $plugin->getSetting($this->contextId, 'dataverse'));
		$this->setData('apiToken', $plugin->getSetting($this->contextId, 'apiToken'));
	}

	function readInputData(): void
	{
		$this->readUserVars(array('dataverseServer', 'dataverse', 'apiToken'));
		$request = PKPApplication::getRequest();
		$this->setData('dataverseServer', $this->normalizeURI($this->getData('dataverseServer')));
		$this->setData('dataverse', $this->normalizeURI($this->getData('dataverse')));
	}

	private function normalizeURI(string $uri): string
	{
		return preg_replace("/\/+$/", '', $uri);
	}

	function fetch($request, $template = null, $display = false)
	{
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('pluginName', $this->plugin->getName());
		return parent::fetch($request);
	}

	function execute(...$functionArgs)
	{
		$plugin = $this->plugin;
		$plugin->updateSetting($this->contextId, 'dataverseServer', $this->getData('dataverseServer'), 'string');
		$plugin->updateSetting($this->contextId, 'dataverse', $this->getData('dataverse'), 'string');
		$plugin->updateSetting($this->contextId, 'apiToken', $this->getData('apiToken'), 'string');
		$plugin->updateSetting($this->contextId, 'apiVersion', $this->getData('apiVersion'), 'string');
		parent::execute(...$functionArgs);
	}

	function validateCredentials(): bool
	{
		$client = new DataverseClient(new DataverseConfiguration($this->getData("apiToken"), $this->getData("dataverseServer"), $this->getData("dataverse")));
		$connectionSuccessful = $client->checkConnectionWithDataverse();

		if ($connectionSuccessful) {
			$dataverseDAO = new DataverseDAO();
			$dataverseDAO->insertCredentialsOnDatabase($this->contextId, $this->getData("dataverseServer"), $this->getData("dataverse"), $this->getData("apiToken"));
		}

		return $connectionSuccessful;
	}
}
