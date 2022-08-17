<?php

import('classes.handler.Handler');

class UploadDatasetHandler extends Handler {

	function uploadDataset($args, $request) {

        AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_SUBMISSION,
			LOCALE_COMPONENT_PKP_USER,
			LOCALE_COMPONENT_PKP_EDITOR,
			LOCALE_COMPONENT_APP_EDITOR
		);

        $plugin = PluginRegistry::getPlugin('generic', 'dataverseplugin');
        $templateMgr = TemplateManager::getManager($request);
        
        import('lib.pkp.classes.linkAction.request.AjaxModal');
        $uploadDatasetAction = new LinkAction(
            'uploadDataset',
            new AjaxModal(
                $request->getRouter()->url($request, null, null, 'uploadDatasetModal', null, $args),
                __('plugins.generic.dataverse.dataCitationLabel'),
                'modal_add_item'
            ),
            __('plugins.generic.dataverse.datasetButton'),
            'add_item',
        );

		$templateMgr->assign('uploadDatasetAction', $uploadDatasetAction);
        return $templateMgr->fetchJson($plugin->getTemplateResource('uploadDataset.tpl'));
    }

    function uploadDatasetModal($args, $request) {
        $plugin = PluginRegistry::getPlugin('generic', 'dataverseplugin');
        $templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('submissionId', $args['submissionId']);
		return $templateMgr->fetchJson($plugin->getTemplateResource('uploadDatasetModal.tpl'));
    }

    function uploadDatasetForm($args, $request) {
        import('plugins.generic.dataverse.classes.form.UploadDatasetForm');
        $plugin = PluginRegistry::getPlugin('generic', 'dataverseplugin');
        $form = new UploadDatasetForm($plugin, $args['submissionId']);
        if ($request->getUserVar('save')) {
            $form->readInputData();
            $form->execute();
            $notificationManager = new NotificationManager();
            $notificationManager->createTrivialNotification($request->getUser()->getId());
            return new JSONMessage(true);
        } else {
            $form->initData();
        }
		return new JSONMessage(true, $form->fetch($request));
    }
}
