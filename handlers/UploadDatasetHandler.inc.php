<?php

import('classes.handler.Handler');
import('plugins.generic.dataverse.classes.form.AddDatasetFileForm');

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

    function addDatasetFiles($args, $request) {

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
            'addDatasetFiles',
            new AjaxModal(
                $request->getRouter()->url($request, null, null, 'addGalley', null, $args),
                __('plugins.generic.dataverse.modal.addFile.titleModal'),
                'modal_add_item'
            ),
            __('plugins.generic.dataverse.modal.addFile.title'),
            'add_item',
        );

		$templateMgr->assign('uploadDatasetAction', $uploadDatasetAction);
        return $templateMgr->fetchJson($plugin->getTemplateResource('uploadDataset.tpl'));
    }

    function addGalley($args, $request) {
        $plugin = PluginRegistry::getPlugin('generic', 'dataverseplugin');
        $submissionId = $args['submissionId'];
        $submission = Services::get('submission')->get($submissionId);
        $publication = $submission->getCurrentPublication();
        $contextId = $submission->getContextId();

		$addDatasetFileForm = new AddDatasetFileForm(
			$plugin,
			$submissionId,
			$publication->getData('id'),
            $contextId
		);
		$addDatasetFileForm->initData();
		return new JSONMessage(true, $addDatasetFileForm->fetch($request));
	}

    function updateDatasetFile($args, $request) {
        $router = $request->getRouter();
		$context = $request->getContext();
		$user = $request->getUser();

		import('lib.pkp.classes.file.TemporaryFileManager');
		$temporaryFileManager = new TemporaryFileManager();
		$temporaryFile = $temporaryFileManager->handleUpload('uploadedFile', $user->getId());
		if ($temporaryFile) {
			$json = new JSONMessage(true);
			$json->setAdditionalAttributes(array(
				'temporaryFileId' => $temporaryFile->getId()
			));
			return $json;
		} else {
			return new JSONMessage(false, __('common.uploadFailed'));
		}
    }

    function saveFile($args, $request) {
        $plugin = PluginRegistry::getPlugin('generic', 'dataverseplugin');
        $submissionId = $args['submissionId'];
        $submission = Services::get('submission')->get($submissionId);
        $publication = $submission->getCurrentPublication();
        $contextId = $submission->getContextId();

		$addDatasetFileForm = new AddDatasetFileForm(
			$plugin,
			$submissionId,
			$publication->getData('id'),
            $contextId
		);

        $addDatasetFileForm->readInputData();

		if ($addDatasetFileForm) {
			$fileId = $addDatasetFileForm->execute();

			// Let the calling grid reload itself
			return DAO::getDataChangedEvent();
		}

		return new JSONMessage(false);
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
