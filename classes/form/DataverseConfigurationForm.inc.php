<?php

import('lib.pkp.classes.form.Form');
import('plugins.generic.dataverse.classes.api.DataverseClient');
import('plugins.generic.dataverse.classes.DataverseDAO');

class DataverseConfigurationForm extends Form {

	private $plugin;
	private $contextId;

	function DataverseConfigurationForm(Plugin $plugin, int $contextId)
	{
		$this->plugin = $plugin;
		$this->contextId = $contextId;

		parent::__construct($plugin->getTemplateResource('dataverseConfigurationForm.tpl'));
		$this->addCheck(new FormValidatorUrl($this, 'dataverseUrl', FORM_VALIDATOR_REQUIRED_VALUE, 'plugins.generic.dataverse.settings.dataverseUrlRequired'));
		$this->addCheck(new FormValidator($this, 'apiToken', FORM_VALIDATOR_REQUIRED_VALUE, 'plugins.generic.dataverse.settings.tokenRequired'));
		$this->addCheck(new FormValidatorCustom($this, 'termsOfUse', FORM_VALIDATOR_REQUIRED_VALUE, 'plugins.generic.dataverse.settings.dataverseUrlNotValid', array($this, 'validateCredentials')));
		$this->addCheck(new FormValidatorPost($this));
	}

	function initData(): void
	{
		$dataverseDAO = new DataverseDAO();
		$credentials = $dataverseDAO->getCredentialsFromDatabase($this->contextId);
		$this->setData('apiToken', $credentials[0]);
		$this->setData('dataverseUrl', $credentials[1]);
		$this->setData('termsOfUse', $credentials[2]);
	}

	function readInputData(): void
	{
		$this->readUserVars(array('dataverseUrl', 'apiToken', 'termsOfUse'));
		$this->setData('dataverseUrl', $this->normalizeURI($this->getData('dataverseUrl')));
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
		$this->plugin->updateSetting($this->contextId, 'dataverseUrl', $this->getData('dataverseUrl'), 'string');
		$this->plugin->updateSetting($this->contextId, 'apiToken', $this->getData('apiToken'), 'string');
		$this->plugin->updateSetting($this->contextId, 'termsOfUse', $this->getData('termsOfUse'));
		parent::execute(...$functionArgs);
	}

	function validateCredentials(): bool
	{
		$client = new DataverseClient(new DataverseConfiguration($this->getData("dataverseUrl"), $this->getData("apiToken")));
		$connectionSuccessful = $client->checkConnectionWithDataverse();

		if ($connectionSuccessful) {
			$dataverseDAO = new DataverseDAO();
			$dataverseDAO->insertCredentialsOnDatabase($this->contextId, $this->getData("dataverseUrl"), $this->getData("apiToken"));
		}

		return $connectionSuccessful;
	}
}
