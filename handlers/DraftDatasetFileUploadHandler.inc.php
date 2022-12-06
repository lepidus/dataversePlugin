<?php

import('classes.handler.Handler');

class DraftDatasetFileUploadHandler extends Handler {

	public function draftDatasetFiles($args, $request) {
		$plugin = PluginRegistry::getPlugin('generic', 'dataverseplugin');
        $dispatcher = $request->getDispatcher();
        $context = $request->getContext();
        $currentUser = $request->getUser();
        $templateMgr = TemplateManager::getManager($request);
        $locale = AppLocale::getLocale();

        $params = [
            'submissionId' => $args['submissionId'],
            'userId' => $currentUser->getId()
        ];

        $temporaryFileApiUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), 'temporaryFiles');
        $draftDatasetFileUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), 'draftDatasetFiles', null, null, $params);
        $apiUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), 'draftDatasetFiles');

        $termsOfUse = DAORegistry::getDAO('DataverseDAO')->getTermsOfUse($context->getId(), $locale);
        $termsOfUseParams = array(
            'dataverseName' => $args['dataverseName'],
            'termsOfUseURL' => $termsOfUse,
        );


        $supportedFormLocales = $context->getSupportedFormLocales();
		$localeNames = AppLocale::getAllLocales();
		$locales = array_map(function($localeKey) use ($localeNames) {
			return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
		}, $supportedFormLocales);

        $plugin->import('classes.form.DraftDatasetFileForm');
		$draftDatasetFileForm = new DraftDatasetFileForm($draftDatasetFileUrl, $locales, $temporaryFileApiUrl, $termsOfUseParams);

        $draftDatasetFileDAO = DAORegistry::getDAO('DraftDatasetFileDAO');
        $draftDatasetFiles = $draftDatasetFileDAO->getBySubmissionId($args['submissionId']);
        
        $props = Services::get('schema')->getFullProps('draftDatasetFile');
        
        $items = [];
        foreach ($draftDatasetFiles as $draftDatasetFile) {
            $draftDatasetFileProps = [];
            foreach ($props as $prop) {
                $draftDatasetFileProps[$prop] = $draftDatasetFile->getData($prop);
            }
            $items[] = $draftDatasetFileProps;
        }
        
        ksort($items);

        $templateMgr->assign('state', [
			'components' => [
                'draftDatasetFilesList' => [
                    'items' => $items
                ],
                'draftDatasetFileForm' => $draftDatasetFileForm->getConfig(),
            ],
            'deleteDraftDatasetFileLabel' => __('plugins.generic.dataverse.modal.deleteDatasetFile'),
            'confirmDeleteMessage' => __('plugins.generic.dataverse.modal.confirmDelete'),
            'apiUrl' => $apiUrl,
            'formErrors' => [
                'termsOfUse' => [
                    __('plugins.generic.dataverse.termsOfUse.error')
                ]
            ]
		]);
        
        return $templateMgr->fetchJson($plugin->getTemplateResource('draftDatasetFiles.tpl'));
    }

}
