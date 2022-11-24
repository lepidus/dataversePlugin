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
		HookRegistry::register('Template::Workflow::Publication', array($this, 'addDatasetDataToWorkflow'));
		HookRegistry::register('TemplateManager::display', array($this, 'loadResourceToWorkflow'));
		HookRegistry::register('PreprintHandler::view', array($this, 'loadResources'));
		HookRegistry::register('LoadComponentHandler', array($this, 'setupDataverseHandlers'));

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
			$output .= $templateMgr->fetch($this->plugin->getTemplateResource('datasetData.tpl'));
		}

		return false;
	}

	public function loadResources(string $hookName, array $params): bool
	{
		$request = $params[0];
		$submission = $params[1];
		$templateManager = TemplateManager::getManager($request);
		$pluginPath = $request->getBaseUrl() . DIRECTORY_SEPARATOR . $this->plugin->getPluginPath();

		$study = $this->getSubmissionStudy($submission);
		if (isset($study)) {
			$this->loadJavaScript($pluginPath, $templateManager);
			$this->addJavaScriptVariables($request, $templateManager, $study);
		}

		return false;
	}

	public function loadJavaScript($pluginPath, $templateManager) {
		$templateManager->addJavaScript(
			'dataverseScripts',
			$pluginPath . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'init.js',
			[
				'contexts' => ['backend', 'frontend']
			]
		);
	}

	function loadResourceToWorkflow(string $hookName, array $params): bool
	{
		$templateMgr = $params[0];
		$template = $params[1];
		

		if ($template == 'workflow/workflow.tpl' || $template == 'authorDashboard/authorDashboard.tpl') {
			$request = Application::get()->getRequest();
			$pluginPath = $request->getBaseUrl() . DIRECTORY_SEPARATOR . $this->plugin->getPluginPath();
			$submission = $templateMgr->get_template_vars('submission');

			$study = $this->getSubmissionStudy($submission);
			if (!empty($study)) {
				$templateMgr->addJavaScript(
					'dataverseWorkflow', 
					$pluginPath . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'init.js',
					[
						'contexts' => ['backend', 'frontend']
					]
				);
				$templateMgr->addJavaScript(
					'dataverseHelper', 
					$pluginPath . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'dataverseHelper.js',
					[
						'inline' => false,
						'contexts' => ['backend']
					]
				);
				$this->addJavaScriptVariables($request, $templateMgr, $study);

				$this->setupDatasetMetadataForm($request, $templateMgr, $study);
				$this->setupDatasetFilesList($request, $templateMgr, $study);
				$this->setupDatasetFileForm($request, $templateMgr, $study);
			}
		}
		return false;
	}

	function addJavaScriptVariables($request, $templateManager, $study): void
	{
		$dispatcher = $request->getDispatcher();
		$context = $request->getContext();
		$configuration = $this->getDataverseConfiguration();
		$apiToken = $configuration->getApiToken();
		$dataverseServer = $configuration->getDataverseServer();

		$persistentUri = $study->getPersistentUri();
		preg_match('/(?<=https:\/\/doi.org\/)(.)*/', $persistentUri, $matches);
		$persistentId =  "doi:" . $matches[0];

		$editUri = "$dataverseServer/api/datasets/:persistentId/?persistentId=$persistentId";
		$apiUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), 'datasets/' . $study->getId());

		$dataverseNotificationMgr = new DataverseNotificationManager();
		$dataverseUrl = $configuration->getDataverseUrl();
		$params = ['dataverseUrl' => $dataverseUrl];
		$errorMessage = $dataverseNotificationMgr->getNotificationMessage(DATAVERSE_PLUGIN_HTTP_STATUS_BAD_REQUEST, $params);

		$data = [
			"editUri" => $editUri,
			"apiToken" => $apiToken,
			"errorMessage" => $errorMessage,
			"datasetApiUrl" => $apiUrl
		];

		$templateManager->addJavaScript('dataverse', 'appDataverse = ' . json_encode($data) . ';', [
			'inline' => true,
			'contexts' => ['backend', 'frontend']
		]);
	}

	function addDatasetDataToWorkflow(string $hookName, array $params): bool
	{
		$templateMgr =& $params[1];
		$output =& $params[2];
		$context = Application::get()->getRequest()->getContext();
		$submission = $templateMgr->get_template_vars('submission');

		$study = $this->getSubmissionStudy($submission);
		if (isset($study)) {
			$output .= sprintf(
				'<tab id="datasetTab" label="%s">%s</tab>',
				__("plugins.generic.dataverse.researchData"),
				$templateMgr->fetch($this->plugin->getTemplateResource('datasetData.tpl'))
			);
		}

		return false;
	}

	private function getSubmissionStudy($submission): ?DataverseStudy
	{
		$dataverseStudyDao =& DAORegistry::getDAO('DataverseStudyDAO');
		$study = $dataverseStudyDao->getStudyBySubmissionId($submission->getId());
		return $study;
	}

	private function setupDatasetMetadataForm($request, $templateMgr, $study): void
	{
		$context = $request->getContext();
		$dispatcher = $request->getDispatcher();

		$apiUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), 'datasets/' . $study->getId());
		$vocabSuggestionUrlBase =$request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'vocabs', null, null, ['vocab' => 'submissionKeyword']);

		$datasetResponse = $this->getDataverseService()->getDatasetResponse($study);
		
		$supportedFormLocales = $context->getSupportedFormLocales();
		$localeNames = AppLocale::getAllLocales();
		$locales = array_map(function($localeKey) use ($localeNames) {
			return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
		}, $supportedFormLocales);

		$this->plugin->import('classes.form.DatasetMetadataForm');
		$datasetMetadataForm = new DatasetMetadataForm($apiUrl, $locales, $datasetResponse, $vocabSuggestionUrlBase);

		$this->addComponent($templateMgr, $datasetMetadataForm);

		$workflowPublicationFormIds = $templateMgr->getState('publicationFormIds');
		$workflowPublicationFormIds[] = FORM_DATASET_METADATA;

		$templateMgr->setState([
			'publicationFormIds' => $workflowPublicationFormIds
		]);
	}

	private function setupDatasetFilesList($request, $templateMgr, $study): void
	{
		$datasetFilesResponse = $this->getDataverseService()->getDatasetFiles($study);
		$datasetFiles = array();

		foreach ($datasetFilesResponse->data as $data) {
			$datasetFiles[] = ["id" => $data->dataFile->id, "title" => $data->label];
		}

		import('plugins.generic.dataverse.classes.listPanel.DatasetFilesListPanel');
		$datasetFilesListPanel = new DatasetFilesListPanel(
			'datasetFiles',
			__('plugins.generic.dataverse.researchData.files'),
			[
				'apiUrl' => '',
				'items' => $datasetFiles
			]
		);

		$this->addComponent($templateMgr, $datasetFilesListPanel);

		$templateMgr->setState([
			'deleteDatasetFileLabel' => __('plugins.generic.dataverse.modal.deleteDatasetFile'),
            'confirmDeleteMessage' => __('plugins.generic.dataverse.modal.confirmDelete'),
		]);
	}

	function setupDatasetFileForm($request, $templateMgr, $study): void
	{
		$dispatcher = $request->getDispatcher();
        $context = $request->getContext();
		$service = $this->getDataverseService();
		$dataverseName = $service->getDataverseName();
		
		$temporaryFileApiUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), 'temporaryFiles');
		$apiUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), 'datasets/' . $study->getId() . '/file');

		$termsOfUseParams = array(
            'dataverseName' => $dataverseName,
            'termsOfUseURL' => $dispatcher->url(
                $request,
                ROUTE_COMPONENT, 
                null,
                'plugins.generic.dataverse.handlers.TermsOfUseHandler',
                'get'
            ),
        );

		$supportedFormLocales = $context->getSupportedFormLocales();
		$localeNames = AppLocale::getAllLocales();
		$locales = array_map(function($localeKey) use ($localeNames) {
			return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
		}, $supportedFormLocales);

		import('plugins.generic.dataverse.classes.form.DraftDatasetFileForm');
		$draftDatasetFileForm = new DraftDatasetFileForm($apiUrl, $locales, $temporaryFileApiUrl, $termsOfUseParams);

		$this->addComponent(
			$templateMgr,
			$draftDatasetFileForm,
			[
				'errors' => [
					'termsOfUse' => [
						__('plugins.generic.dataverse.termsOfUse.error')
					]
				]
			]
		);
	}

	private function addComponent($templateMgr, $component, $args = []): void
	{
		$workflowComponents = $templateMgr->getState('components');
		$workflowComponents[$component->id] = $component->getConfig();

		if (!empty($args)) {
			foreach ($args as $prop => $value) {
				$workflowComponents[$component->id][$prop] = $value;
			}
		}

		$templateMgr->setState([
			'components' => $workflowComponents
		]);
	}

	function setupDataverseHandlers($hookName, $params): bool
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
