<?php

import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');
import('plugins.generic.dataverse.classes.APACitation');
import('plugins.generic.dataverse.handlers.TermsOfUseHandler');
import('lib.pkp.classes.submission.SubmissionFile');

class TemplateDispatcher extends DataverseDispatcher
{
    public function __construct(Plugin $plugin)
	{
		HookRegistry::register('submissionsubmitstep2form::display', array($this, 'handleDatasetModal'));
        HookRegistry::register('submissionfilesmetadataform::display', array($this, 'handleSubmissionFilesMetadataFormDisplay'));
		HookRegistry::register('submissionfilesmetadataform::execute', array($this, 'handleSubmissionFilesMetadataFormExecute'));
		HookRegistry::register('Templates::Preprint::Details', array($this, 'addDataCitationSubmission'));
		HookRegistry::register('TemplateManager::display', array($this, 'changeGalleysLinks'));
		HookRegistry::register('LoadComponentHandler', array($this, 'setupTermsOfUseHandler'));

		parent::__construct($plugin);
    }

	function handleDatasetModal(string $hookName, array $params): bool
	{
		$form =& $params[0];
		$form->readUserVars(array('submissionId'));
		$submissionId = $form->getData('submissionId');
		$submission = Services::get('submission')->get($submissionId);
		$request = PKPApplication::get()->getRequest();
		$galleys = $submission->getGalleys();
		$datasetGalleys = array();
		foreach ($galleys as $galley) {
			$submissionFile = Services::get('submissionFile')->get($galley->getData('submissionFileId'));
			if ($submissionFile) {
				$DATASET_GENRE_ID = 7;
				if ($submissionFile->getGenreId() == $DATASET_GENRE_ID)
					array_push($datasetGalleys, $galley);
			}
		}
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('datasetGalleys', $datasetGalleys);
		$templateMgr->registerFilter("output", array($this, 'sendDataset'));

		return false;
	}

	function sendDataset(string $output, Smarty_Internal_Template $templateMgr): string {
		if (preg_match('/<div[^>]+class="[^>]*formButtons[^>]*"[^>]*>(.|\n)*?<\/div>/', $output, $matches, PREG_OFFSET_CAPTURE)) {
			$template = $templateMgr->fetch($this->plugin->getTemplateResource('sendDataset.tpl'));
			$templateMgr->unregisterFilter('output', array($this, 'sendDataset'));
			$service = $this->getDataverseService();
			$dataverseName = $service->getDataverseName();
			$newOutput = str_replace("{\$dataverseName}", $dataverseName, $template);
			$output = $newOutput . $output;
		}
		return $output;
	}


    function handleSubmissionFilesMetadataFormDisplay(string $hookName, array $params): bool
	{
		$request = PKPApplication::get()->getRequest();

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->registerFilter("output", array($this, 'publishDataFormFilter'));
		return false;
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
			$apaCitation = new APACitation();
			$dataCitation = $apaCitation->getCitationAsMarkupByStudy($study);
			$templateMgr->assign('dataCitation', $dataCitation);
			$output .= $templateMgr->fetch($this->plugin->getTemplateResource('dataCitationSubmission.tpl'));
		}

		return false;
	}

	function changeGalleysLinks(string $hookName, array $params)
	{
		$smarty = $params[0];
		$template = $params[1];

		$templateMapping = [
			$template => 'frontend/pages/preprint.tpl',
			$template => 'frontend/pages/indexJournal.tpl'
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
		if ($component == 'plugins.generic.dataverse.handlers.TermsOfUseHandler') {
			return true;
		}
		return false;
	}
}
