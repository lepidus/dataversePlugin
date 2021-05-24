<?php

import('lib.pkp.classes.form.Form');
import('plugins.generic.dataversePlugin.classes.DataverseDAO');
import('plugins.generic.dataversePlugin.classes.DataverseRepository');

class DataverseAuthForm extends Form {

	private $plugin;

	private $contextId;

	function DataverseAuthForm($plugin, $contextId) {
		$this->plugin = $plugin;
		$this->contextId = $contextId;

		parent::__construct($plugin->getTemplateResource('dataverseAuthForm.tpl'));
		$this->addCheck(new FormValidatorUrl($this, 'dvnUri', FORM_VALIDATOR_REQUIRED_VALUE, 'plugins.generic.dataverse.settings.dvnUriRequired'));
		$this->addCheck(new FormValidator($this, 'apiToken', FORM_VALIDATOR_REQUIRED_VALUE, 'plugins.generic.dataverse.settings.tokenRequired'));
		$this->addCheck(new FormValidatorCustom($this, '', FORM_VALIDATOR_REQUIRED_VALUE, 'plugins.generic.dataverse.settings.dvnUriNotValid', array($this, 'checkConnectionWithDataverseInstance')));
		$this->addCheck(new FormValidatorPost($this));
	}

	function initData() {
		$plugin = $this->plugin;
		$this->setData('dvnUri', $plugin->getSetting($this->contextId, 'dvnUri'));		 
		$this->setData('apiToken', $plugin->getSetting($this->contextId, 'apiToken'));
	}

	function readInputData() {
		$this->readUserVars(array('dvnUri', 'apiToken'));
		$request = PKPApplication::getRequest();
		$this->setData('dvnUri', preg_replace("/\/+$/", '', $this->getData('dvnUri')));
	}

	function fetch($request, $template = NULL, $display = false) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('pluginName', $this->plugin->getName());
		return parent::fetch($request);
	}

	function execute(...$functionArgs) {
		$plugin = $this->plugin;
		$plugin->updateSetting($this->contextId, 'dvnUri', $this->getData('dvnUri'), 'string');
		$plugin->updateSetting($this->contextId, 'apiToken', $this->getData('apiToken'), 'string');
		$plugin->updateSetting($this->contextId, 'apiVersion', $this->getData('apiVersion'), 'string');
		parent::execute(...$functionArgs);
	}

	public function checkConnectionWithDataverseInstance() {
		$this->setData('apiVersion', '1');
		
		$serviceDocumentRequest = preg_match('/\/dvn$/', $this->getData('dvnUri')) ? '' : '/dvn';
		$serviceDocumentRequest .= '/api/data-deposit/v'. $this->getData('apiVersion') . '/swordv2/service-document';

		$dataverseRepository = new DataverseRepository();
		$dataverseConnectionStatus = $dataverseRepository->validateCredentials($this, $serviceDocumentRequest);
		
		if ($dataverseConnectionStatus) {
			$dataverseDAO = new DataverseDAO();
			$dataverseDAO->insertCredentialsOnDatabase($this->contextId, $this->getData('dvnUri'), $this->getData('apiToken'));
		}

		return ($dataverseConnectionStatus);
	}
}
