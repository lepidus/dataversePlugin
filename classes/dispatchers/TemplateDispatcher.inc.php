<?php

import('plugins.generic.dataverse.classes.dispatchers.DataverseDispatcher');
import('plugins.generic.dataverse.classes.APACitation');
import('plugins.generic.dataverse.handlers.TermsOfUseHandler');

class TemplateDispatcher extends DataverseDispatcher
{
    public function __construct(Plugin $plugin)
	{
        HookRegistry::register('submissionfilesmetadataform::display', array($this, 'handleSubmissionFilesMetadataFormDisplay'));
		HookRegistry::register('submissionfilesmetadataform::execute', array($this, 'handleSubmissionFilesMetadataFormExecute'));
		HookRegistry::register('Templates::Preprint::Main', array($this, 'addDataCitationSubmission'));
		HookRegistry::register('LoadComponentHandler', array($this, 'setupTermsOfUseHandler'));

		parent::__construct($plugin);
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

			$request = PKPApplication::get()->getRequest();
			$termsOfUseURL = $request->getDispatcher()->url($request, ROUTE_PAGE) . '/$$$call$$$/plugins/generic/dataverse/handlers/terms-of-use/get';
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

	function setupTermsOfUseHandler($hookName, $params) {
		$component = &$params[0];
		if ($component == 'plugins.generic.dataverse.handlers.TermsOfUseHandler') {
			return true;
		}
		return false;
	}
}
