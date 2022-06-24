<?php

import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');
import('plugins.generic.dataverse.classes.creators.DataverseServiceFactory');
import('plugins.generic.dataverse.classes.study.DataverseStudyDAO');

class DataverseServiceDispatcher extends DataverseDispatcher
{
    public function __construct(Plugin $plugin)
	{
		HookRegistry::register('Schema::get::submissionFile', array($this, 'modifySubmissionFileSchema'));
		HookRegistry::register('submissionsubmitstep4form::validate', array($this, 'dataverseDepositOnSubmission'));
		HookRegistry::register('Publication::publish', array($this, 'publishDeposit'));
		HookRegistry::register('PreprintHandler::view', array($this, 'loadResources'));

		parent::__construct($plugin);
    }

	public function loadResources($hookName, $params) {
		$request = $params[0];
		$submission = $params[1];
		$templateManager = TemplateManager::getManager($request);
		$pluginPath = $request->getBaseUrl() . DIRECTORY_SEPARATOR . $this->plugin->getPluginPath();
		
		$this->loadJavaScript($pluginPath, $templateManager);
		$this->addJavaScriptVariables($request, $templateManager, $submission);
	}

	public function loadJavaScript($pluginPath, $templateManager) {
		$templateManager->addJavaScript("Dataverse",  $pluginPath . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'init.js', array(
			'contexts' => ['backend', 'frontend']
		));
	}

	function addJavaScriptVariables($request, $templateManager, $submission) {
		$configuration = $this->getDataverseConfiguration();
		$apiToken = $configuration->getApiToken();

		$dataverseServer = $configuration->getDataverseServer();
		$dataverseStudyDao =& DAORegistry::getDAO('DataverseStudyDAO');
		$study = $dataverseStudyDao->getStudyBySubmissionId($submission->getId());
		$persistentUri = $study->getPersistentUri();
		preg_match('/(?<=https:\/\/doi.org\/)(.)*/', $persistentUri, $matches); 
		$persistentId =  "doi:" . $matches[0];

		$editUri = "$dataverseServer/api/datasets/:persistentId/?persistentId=$persistentId";

		$dataverseNotificationMgr = new DataverseNotificationManager();
		$dataverseUrl = $configuration->getDataverseUrl();
		$params = ['dataverseUrl' => $dataverseUrl];
		$errorMessage = $dataverseNotificationMgr->getNotificationMessage(DATAVERSE_PLUGIN_HTTP_STATUS_BAD_REQUEST, $params);

		$data = [
			"editUri" => $editUri,
			"apiToken" => $apiToken,
			"errorMessage" => $errorMessage
		];

		$templateManager->addJavaScript('dataverse', 'appDataverse = ' . json_encode($data) . ';', [
			'inline' => true,
			'contexts' => ['backend', 'frontend']
		]);
	}

    public function modifySubmissionFileSchema(string $hookName, array $params): bool
	{
		$schema =& $params[0];
        $schema->properties->{'publishData'} = (object) [
            'type' => 'boolean',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];
        return false;
	}

    function dataverseDepositOnSubmission(string $hookName, array $params): void {
		$form =& $params[0];
        $submission = $form->submission;

		$service = $this->getDataverseService();
		$service->setSubmission($submission);
		if($service->hasDataSetComponent()){
			$service->depositPackage();
		}
	}

	function publishDeposit(string $hookName, array $params): void {
		$submission = $params[2];
		
		$service = $this->getDataverseService();
		$service->setSubmission($submission);
	}
}
