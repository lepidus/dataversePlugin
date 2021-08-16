<?php
/**
 * @file plugins/generic/dataverse/DataversePlugin.inc.php
 *
 * Copyright (c) 2019-2021 Lepidus Tecnologia
 * Copyright (c) 2020-2021 SciELO
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class DataversePlugin
 * @ingroup plugins_generic_dataverse
 *
 * @brief dataverse plugin class
 */

import('lib.pkp.classes.plugins.GenericPlugin');
import('classes.notification.NotificationManager');
import('plugins.generic.dataverse.classes.creators.DataversePackageCreator');
import('plugins.generic.dataverse.classes.creators.SubmissionAdapterCreator');
import('plugins.generic.dataverse.classes.creators.DatasetBuilder');
require('plugins/generic/dataverse/libs/swordappv2-php-library/swordappclient.php');

class DataversePlugin extends GenericPlugin {

	private const DATASET_GENRE_ID = 7;

	/**
	 * @see LazyLoadPlugin::register()
	 */
	public function register($category, $path, $mainContextId = NULL) {
		$success = parent::register($category, $path, $mainContextId);
		HookRegistry::register('submissionsubmitstep4form::validate', array($this, 'createMetadataPackage'));
		return $success;
	}

	/**
	 * @see PKPPlugin::getDisplayName()
	 */
	public function getDisplayName() {
		return __('plugins.generic.dataverse.displayName');
	}

	/**
	 * @see PKPPlugin::getDescription()
	 */
	public function getDescription() {
		return __('plugins.generic.dataverse.description');
	}
	
	/**
	 * @see Plugin::getActions()
	 */
	function getActions($request, $actionArgs) {
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		return array_merge(
			$this->getEnabled() ? array(
				new LinkAction(
					'settings',
					new AjaxModal(
						$router->url($request, null, null, 'manage', null, array('verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic')),
						$this->getDisplayName()
					),
					__('manager.plugins.settings'),
					null
				),
			) : array(),
			parent::getActions($request, $actionArgs)
		);
	}

	/**
	 * @see Plugin::manage()
	 */
	function manage($args, $request) {
		switch ($request->getUserVar('verb')) {
			case 'settings':
				$context = $request->getContext();
				$contextId = ($context == null) ? 0 : $context->getId();

				$this->import('classes.form.DataverseAuthForm');
				$form = new DataverseAuthForm($this, $contextId);
				if ($request->getUserVar('save')) {
					$form->readInputData();
					if ($form->validate()) {
						$form->execute();
						$notificationManager = new NotificationManager();
						$notificationManager->createTrivialNotification($request->getUser()->getId());
						return new JSONMessage(true);
					}
				} else {
					$form->initData();
					$form->display();
				}
				
				return new JSONMessage(true, $form->fetch($request));
		}
		return parent::manage($args, $request);
	}

	function createMetadataPackage($hookName, $params){
        $form =& $params[0];
        $submission = $form->submission;
		$locale = $submission->getLocale();

        $galleys = $submission->getGalleys();

		$galleysFiles;
		$galleysFilesGenres;
		foreach ($galleys as $galley){
			$galleysFiles[] = $galley->getFile();
			$galleysFilesGenres[] = $galley->getFile()->getGenreId();
		}

		if (in_array(self::DATASET_GENRE_ID, $galleysFilesGenres)) {
			$packageCreator = new DataversePackageCreator();
			$submissionAdapterCreator = new SubmissionAdapterCreator();
			$datasetBuilder = new DatasetBuilder();

			$submissionAdapter = $submissionAdapterCreator->createSubmissionAdapter($submission, $locale);
			$datasetModel = $datasetBuilder->build($submissionAdapter);

			$packageCreator->loadMetadata($datasetModel);
			$packageCreator->createAtomEntry();

			$publicFilesDir = Config::getVar('files', 'files_dir');
			foreach($galleysFiles as $galleysFile) {
				$galleysFilePath = $publicFilesDir . DIRECTORY_SEPARATOR  . $galleysFile->getLocalizedData('path');
				$packageCreator->addFileToPackage($galleysFilePath, $galleysFile->getLocalizedData('name'));
			}

			$packageCreator->createPackage();
		}

		return;
	}
}

?>
