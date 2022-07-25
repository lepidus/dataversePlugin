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
                $request->getRouter()->url($request, null, null, 'uploadDatasetForm', null, null),
                'teste',
                'modal_add_item'
            ),
            __('plugins.generic.dataverse.uploadDataset'),
            'add_item'
        );

		$templateMgr->assign('uploadDatasetAction', $uploadDatasetAction);
        return $templateMgr->fetchJson($plugin->getTemplateResource('uploadDataset.tpl'));
    }

    function uploadDatasetForm($args, $request) {
        import('plugins.generic.dataverse.classes.form.UploadDatasetForm');
        $form = new UploadDatasetForm();
        $form->initData();
		return new JSONMessage(true, $form->fetch($request));
    }
}
