<?php

import('classes.handler.Handler');

class DraftDatasetFileUploadHandler extends Handler {

	public function draftDatasetFiles($args, $request) {
		$plugin = PluginRegistry::getPlugin('generic', 'dataverseplugin');
        $dispatcher = $request->getDispatcher();
        $context = $request->getContext();
        $currentUser = Application::get()->getRequest()->getUser();
        $templateMgr = TemplateManager::getManager($request);

        $params = [
            'submissionId' => $args['submissionId'],
            'userId' => $currentUser->getId()
        ];

        $temporaryFileApiUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), 'temporaryFiles');
        $draftDatasetFileUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), 'draftDatasetFiles', null, null, $params);
        $apiUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), 'draftDatasetFiles');

        $supportedFormLocales = $context->getSupportedFormLocales();
		$localeNames = AppLocale::getAllLocales();
		$locales = array_map(function($localeKey) use ($localeNames) {
			return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
		}, $supportedFormLocales);

        $plugin->import('classes.form.DraftDatasetFileForm');
		$draftDatasetFileForm = new DraftDatasetFileForm($draftDatasetFileUrl, $locales, $temporaryFileApiUrl);

        $draftDatasetFileDAO = DAORegistry::getDAO('DraftDatasetFileDAO');
        $draftDatasetFilesIterator = $draftDatasetFileDAO->getBySubmissionId($args['submissionId']);
        
        $props = Services::get('schema')->getFullProps('draftDatasetFile');
        
        $draftDatasetFiles = [];
        if ($draftDatasetFilesIterator->valid()) {
			foreach ($draftDatasetFilesIterator as $draftDatasetFile) {
                $draftDatasetFileProps = [];
                foreach ($props as $prop) {
                    $draftDatasetFileProps[$prop] = $draftDatasetFile->getData($prop);
                }
				$draftDatasetFiles[] = $draftDatasetFileProps;
			}
		}
        ksort($draftDatasetFiles);

        $templateMgr->assign('state', [
			'components' => [
                'draftDatasetFilesList' => [
                    'items' => $draftDatasetFiles
                ],
                'draftDatasetFileForm' => $draftDatasetFileForm->getConfig(),
            ],
            'deletedraftDatasetFileLabel' => 'Delete dataset file',
            'confirmDeleteMessage' => 'Delete selected dataset file?',
            'apiUrl' => $apiUrl,
		]);
        
        return $templateMgr->fetchJson($plugin->getTemplateResource('draftDatasetFiles.tpl'));
    }

}
