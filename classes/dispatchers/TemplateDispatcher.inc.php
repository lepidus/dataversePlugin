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
		HookRegistry::register('submissionsubmitstep2form::display', array($this, 'handleDatasetModal'));
		HookRegistry::register('uploaddatasetform::display', array($this, 'createDatasetModalStructure'));
		HookRegistry::register('submissionfilesmetadataform::execute', array($this, 'handleSubmissionFilesMetadataFormExecute'));
		HookRegistry::register('Templates::Preprint::Details', array($this, 'addDataCitationSubmission'));
		HookRegistry::register('Template::Workflow::Publication', array($this, 'addDataCitationSubmissionToWorkflow'));
		HookRegistry::register('TemplateManager::display', array($this, 'changeGalleysLinks'));
		HookRegistry::register('TemplateManager::display', array($this, 'loadResourceToWorkflow'));
		HookRegistry::register('LoadComponentHandler', array($this, 'setupTermsOfUseHandler'));

		parent::__construct($plugin);
    }

	function createDatasetModalStructure(string $hookName, array $params)
	{
		$form =& $params[0];
		$form->readUserVars(array('submissionId'));
		$submissionId = $form->getData('submissionId');
		$submission = Services::get('submission')->get($submissionId);
		$request = PKPApplication::get()->getRequest();
		$galleys = $submission->getGalleys();
		$dataset = array();
		$genreDAO = DAORegistry::getDAO('GenreDAO');
		foreach ($galleys as $galley) {
			$submissionFile = Services::get('submissionFile')->get($galley->getData('submissionFileId'));
			if ($submissionFile) {
				$genreName = $genreDAO->getById($submissionFile->getGenreId())->getLocalizedName();
				array_push($dataset, [$genreName, $galley]);
			}
		}

		$service = $this->getDataverseService();
		$dataverseName = $service->getDataverseName();
		$termsOfUseURL = $request->getDispatcher()->url($request, ROUTE_PAGE) . '/$$$call$$$/plugins/generic/dataverse/handlers/terms-of-use/get';

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('dataset', $dataset);
		$templateMgr->assign('dataverseName', $dataverseName);
		$templateMgr->assign('termsOfUseURL', $termsOfUseURL);
		
		return false;
	}

	function handleDatasetModal(string $hookName, array $params): bool
	{
		$request = PKPApplication::get()->getRequest();
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->registerFilter("output", array($this, 'datasetModalFilter'));
		return false;
	}

	function datasetModalFilter(string $output, Smarty_Internal_Template $templateMgr): string {
		if (preg_match('/<div[^>]+class="[^>]*formButtons[^>]*"[^>]*>(.|\n)*?<\/div>/', $output, $matches, PREG_OFFSET_CAPTURE)) {
			$newOutput = substr($output, 0, $offset + strlen($match));
			$newOutput .= $templateMgr->fetch($this->plugin->getTemplateResource('datasetModal.tpl'));
			$newOutput .= substr($output, $offset + strlen($match));
			$output = $newOutput;
			$templateMgr->unregisterFilter('output', array($this, 'datasetModalFilter'));
		}
		return $output;
	}

	function publishDataFormFilter(string $output, Smarty_Internal_Template $templateMgr): string
	{
		if (preg_match('/<input[^>]+name="language"[^>]*>(.|\n)*?<\/div>(.|\n)*?<\/div>/', $output, $matches, PREG_OFFSET_CAPTURE)) {
			$match = $matches[0][0];
			$offset = $matches[0][1];

			$newOutput = substr($output, 0, $offset + strlen($match));
			$newOutput .= $templateMgr->fetch($this->plugin->getTemplateResource('publishDataForm.tpl'));

			$service = $this->getDataverseService();
			$dataverseName = $service->getDataverseName();

			$request = PKPApplication::get()->getRequest();
			$termsOfUseURL = $request->getDispatcher()->url($request, ROUTE_PAGE) . '/$$$call$$$/plugins/generic/dataverse/handlers/terms-of-use/get';

			$newOutput = str_replace("{\$dataverseName}", $dataverseName, $newOutput);
			$newOutput = str_replace("{\$termsOfUseURL}", $termsOfUseURL, $newOutput);

			$newOutput .= substr($output, $offset + strlen($match));
			$output = $newOutput;
			$templateMgr->unregisterFilter('output', array($this, 'publishDataFormFilter'));
		}
		return $output;
	}

	function handleSubmissionFilesMetadataFormExecute(string $hookName, array $params): void
	{
		$form =& $params[0];
		$form->readUserVars(array('publishData'));
		$submissionFile = $form->getSubmissionFile();

		$newSubmissionFile = Services::get('submissionFile')->edit(
			$form->getSubmissionFile(),
			['publishData' => $form->getData('publishData') ? true : false],
			Application::get()->getRequest()
		);
	}

	function addDataCitationSubmission(string $hookName, array $params): bool {
		$templateMgr =& $params[1];
		$output =& $params[2];

		$submission = $templateMgr->getTemplateVars('preprint');
		$dataverseStudyDao = DAORegistry::getDAO('DataverseStudyDAO');
		$study = $dataverseStudyDao->getStudyBySubmissionId($submission->getId());

		if(isset($study)) {
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

		if (array_key_exists($template, $templateMapping)){
			$request = Application::get()->getRequest();
			$pluginPath = $request->getBaseUrl() . DIRECTORY_SEPARATOR . $this->plugin->getPluginPath();
			$submission = $smarty->get_template_vars('submission');
			
			if ($submission) {
				$smarty->addJavaScript("Dataverse_Workflow",  $pluginPath . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'init.js', array(
					'contexts' => ['backend', 'frontend']
				));
				$this->addJavaScriptVariables($request, $smarty, $submission);
			}
		}
		else {
			return false;
		}
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

	function addDataCitationSubmissionToWorkflow(string $hookName, array $params): bool {
		$smarty =& $params[1];
		$output =& $params[2];
		$dataverseStudyDao = DAORegistry::getDAO('DataverseStudyDAO');
		$submission = $smarty->get_template_vars('submission');
		$this->studyDao = new DataverseStudyDAO();
		$study = $this->studyDao->getStudyBySubmissionId($submission->getId());

		if(isset($study)) {
			$output .= sprintf(
				'<tab id="datasetTab" label="%s">%s</tab>',
				__("plugins.generic.dataverse.dataCitationLabel"),
				$smarty->fetch($this->plugin->getTemplateResource('dataCitationSubmission.tpl'))
			);
		}

		return false;
	}

	function changeGalleysLinks(string $hookName, array $params)
	{
		$smarty = $params[0];
		$template = $params[1];

		$templateMapping = [
			$template => "frontend/pages/preprint.tpl",
			$template => "frontend/pages/indexJournal.tpl"
		];

		if (array_key_exists($template, $templateMapping)){
			$smarty->registerFilter("output", array($this, 'galleyLinkFilter'));
		}
		else {
			return false;
		}
	}

	function galleyLinkFilter(string $output, Smarty_Internal_Template $templateMgr): string
	{
		$offset = 0;
		$foundGalleyLinks = false;
		while(preg_match('/<a[^>]+class="obj_galley_link[^>]*"[^>]+href="([^>]+)"*>[^<]+<\/a>/', $output, $matches, PREG_OFFSET_CAPTURE, $offset)) {
			$foundGalleyLinks = true;
			$matchAll = $matches[0][0];
			$posMatchAll = $matches[0][1];
			$linkGalley = $matches[1][0];

			$galleyId = (int) substr($linkGalley, strrpos($linkGalley, '/')+1);
			$galleyService = Services::get('galley');
			$galley = $galleyService->get($galleyId);
			$submissionFile = $galley->getFile();
			$dataverseFileDAO = DAORegistry::getDAO('DataverseFileDAO');
			$dataverseFile = $dataverseFileDAO->getBySubmissionFileId($submissionFile->getId());

			if(!empty($dataverseFile)) {
				$output = substr_replace($output, "", $posMatchAll, strlen($matchAll));
				$offset = $posMatchAll;
			}
			else {
				$offset = $posMatchAll + strlen($matchAll);
			}
		}

		if($foundGalleyLinks) $templateMgr->unregisterFilter('output', array($this, 'galleyLinkFilter'));
		return $output;
	}

	function setupTermsOfUseHandler($hookName, $params) {
		$component = &$params[0];
		switch ($component) {
			case 'plugins.generic.dataverse.handlers.TermsOfUseHandler':
			case 'plugins.generic.dataverse.handlers.UploadDatasetHandler':
				return true;
				break;
		}
		return false;
	}
}
