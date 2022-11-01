<?php

import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');
import('plugins.generic.dataverse.classes.APACitation');
import('plugins.generic.dataverse.handlers.TermsOfUseHandler');
import('lib.pkp.classes.submission.SubmissionFile');
import('plugins.generic.dataverse.classes.study.DataverseStudyDAO');

class TemplateDispatcher extends DataverseDispatcher
{
	public function __construct(Plugin $plugin)
	{
		HookRegistry::register('submissionsubmitstep2form::display', array($this, 'addDraftDatasetFilesContainer'));
		HookRegistry::register('TemplateManager::display', array($this, 'loadDraftDatasetFilePageComponent'));
		HookRegistry::register('Templates::Preprint::Details', array($this, 'addDataCitationSubmission'));
		HookRegistry::register('Template::Workflow::Publication', array($this, 'addDataCitationSubmissionToWorkflow'));
		HookRegistry::register('TemplateManager::display', array($this, 'loadResourceToWorkflow'));
		HookRegistry::register('LoadComponentHandler', array($this, 'setupTermsOfUseHandler'));

		parent::__construct($plugin);
	}
	
	public function loadDraftDatasetFilePageComponent(string $hookName, array $params): bool
	{
		$templateMgr = &$params[0];
        $request = PKPApplication::get()->getRequest();

        $templateMgr->addJavaScript(
            'draftDatasetFilePage',
            $request->getBaseUrl() . DIRECTORY_SEPARATOR . $this->plugin->getPluginPath() . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'DraftDatasetFilesPage.js',
            [
                'contexts' => ['backend'],
                'priority' => STYLE_SEQUENCE_LAST,
            ]
        );

		return false;
	}

	public function addDraftDatasetFilesContainer(string $hookName, array $params): bool
	{
		$request = PKPApplication::get()->getRequest();
		$templateMgr = TemplateManager::getManager($request);

		$form = $params[0];
		$form->readUserVars(array('submissionId'));
		$submissionId = $form->getData('submissionId');

		$service = $this->getDataverseService();
		$dataverseName = $service->getDataverseName();

		$templateMgr->assign('submissionId', $submissionId);
		$templateMgr->assign('dataverseName', $dataverseName);

		$templateMgr->registerFilter("output", array($this, 'draftDatasetFilesContainerFilter'));

		return false;
    }

	public function draftDatasetFilesContainerFilter(string $output, Smarty_Internal_Template $templateMgr): string
	{
		if (
			preg_match('/<div[^>]+class="section formButtons form_buttons[^>]*"[^>]*>/', $output, $matches, PREG_OFFSET_CAPTURE)
			&& $templateMgr->template_resource == 'submission/form/step2.tpl'
		) {
			$datasetFilesContainer = $this->getDraftDatasetFilesContainer();
            $newOutput = $templateMgr->fetch('string:' . $datasetFilesContainer);
			$newOutput .= $output;
			$output = $newOutput;
			$templateMgr->unregisterFilter('output', array($this, 'datasetFileFormFilter'));
		}

		return $output;
	}

    private function getDraftDatasetFilesContainer(): string
	{
        return '
            {capture assign=draftDatasetFileFormUrl}
                {url 
                    router=$smarty.const.ROUTE_COMPONENT 
                    component="plugins.generic.dataverse.handlers.DraftDatasetFileUploadHandler" 
                    op="draftDatasetFiles"
                    submissionId=$submissionId
					dataverseName=$dataverseName
                    escape=false
                }
            {/capture}
            {load_url_in_div id=""|uniqid|escape url=$draftDatasetFileFormUrl}
        ';
    }

	function addDataCitationSubmission(string $hookName, array $params): bool
	{
		$templateMgr =& $params[1];
		$output =& $params[2];

		$submission = $templateMgr->getTemplateVars('preprint');
		$dataverseStudyDao = DAORegistry::getDAO('DataverseStudyDAO');
		$study = $dataverseStudyDao->getStudyBySubmissionId($submission->getId());

		if (isset($study)) {
			$output .= $templateMgr->fetch($this->plugin->getTemplateResource('dataCitationSubmission.tpl'));
		}

		return false;
	}

	function loadResourceToWorkflow(string $hookName, array $params)
	{
		$smarty = $params[0];
		$template = $params[1];

		$templateMapping = [
			$template => "workflow/workflow.tpl",
		];

		if (array_key_exists($template, $templateMapping)) {
			$request = Application::get()->getRequest();
			$pluginPath = $request->getBaseUrl() . DIRECTORY_SEPARATOR . $this->plugin->getPluginPath();
			$submission = $smarty->get_template_vars('submission');

			if ($submission) {
				$smarty->addJavaScript("Dataverse_Workflow",  $pluginPath . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'init.js', array(
					'contexts' => ['backend', 'frontend']
				));
				$this->addJavaScriptVariables($request, $smarty, $submission);
			}
		} else {
			return false;
		}
	}

	function addJavaScriptVariables($request, $templateManager, $submission)
	{
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

	function addDataCitationSubmissionToWorkflow(string $hookName, array $params): bool
	{
		$smarty =& $params[1];
		$output =& $params[2];
		$dataverseStudyDao = DAORegistry::getDAO('DataverseStudyDAO');
		$submission = $smarty->get_template_vars('submission');
		$this->studyDao = new DataverseStudyDAO();
		$study = $this->studyDao->getStudyBySubmissionId($submission->getId());

		if (isset($study)) {
			$output .= sprintf(
				'<tab id="datasetTab" label="%s">%s</tab>',
				__("plugins.generic.dataverse.dataCitationLabel"),
				$smarty->fetch($this->plugin->getTemplateResource('dataCitationSubmission.tpl'))
			);
		}

		return false;
	}

	function setupTermsOfUseHandler($hookName, $params)
	{
		$component =& $params[0];
		switch ($component) {
			case 'plugins.generic.dataverse.handlers.TermsOfUseHandler':
			case 'plugins.generic.dataverse.handlers.DraftDatasetFileUploadHandler':
				return true;
				break;
		}
		return false;
	}
}
