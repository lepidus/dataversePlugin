<?php

import('classes.handler.Handler');

class DataverseHandler extends Handler
{
    public function draftDatasetFiles($args, $request): JSONMessage
    {
        $plugin = PluginRegistry::getPlugin('generic', 'dataverseplugin');
        $dispatcher = $request->getDispatcher();
        $context = $request->getContext();
        $currentUser = $request->getUser();
        $templateMgr = TemplateManager::getManager($request);

        AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION);

        $params = [
            'submissionId' => $args['submissionId'],
            'userId' => $currentUser->getId()
        ];

        $temporaryFileApiUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), 'temporaryFiles');
        $draftDatasetFileUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), 'draftDatasetFiles', null, null, $params);
        $apiUrl = $dispatcher->url($request, ROUTE_API, $context->getPath(), 'draftDatasetFiles');

        $supportedFormLocales = $context->getSupportedFormLocales();
        $localeNames = AppLocale::getAllLocales();
        $locales = array_map(function ($localeKey) use ($localeNames) {
            return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
        }, $supportedFormLocales);

        $plugin->import('classes.form.DraftDatasetFileForm');
        $draftDatasetFileForm = new DraftDatasetFileForm($draftDatasetFileUrl, $context, $locales, $temporaryFileApiUrl);

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
