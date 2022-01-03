<?php

class DataverseFormController
{
    private Plugin $plugin;

    public function __construct(Plugin $plugin)
	{
        $this->plugin = $plugin;
		HookRegistry::register('Schema::get::submissionFile', array($this, 'modifySubmissionFileSchema'));
        HookRegistry::register('submissionfilesmetadataform::display', array($this, 'handleSubmissionFilesMetadataFormDisplay'));
		HookRegistry::register('submissionfilesmetadataform::execute', array($this, 'handleSubmissionFilesMetadataFormExecute'));
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
}