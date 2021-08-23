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
import('plugins.generic.dataverse.classes.DataverseClient');
require('plugins/generic/dataverse/libs/swordappv2-php-library/swordappclient.php');

define('DATASET_GENRE_ID', 7);

class DataversePlugin extends GenericPlugin {

	/**
	 * @see LazyLoadPlugin::register()
	 */
	public function register($category, $path, $mainContextId = NULL) {
		$success = parent::register($category, $path, $mainContextId);
		HookRegistry::register('submissionsubmitstep4form::validate', array($this, 'depositPackage'));
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
	
	function createPackage($submission, $galleysFiles){
		$package = new DataversePackageCreator();
		$submissionAdapterCreator = new SubmissionAdapterCreator();
		$datasetBuilder = new DatasetBuilder();
		$submissionAdapter = $submissionAdapterCreator->createSubmissionAdapter($submission);
		$datasetModel = $datasetBuilder->build($submissionAdapter);
		$package->loadMetadata($datasetModel);
		$package->createAtomEntry();

		$publicFilesDir = Config::getVar('files', 'files_dir');
		foreach($galleysFiles as $galleysFile) {
			$galleysFilePath = $publicFilesDir . DIRECTORY_SEPARATOR  . $galleysFile->getLocalizedData('path');
			$package->addFileToPackage($galleysFilePath, $galleysFile->getLocalizedData('name'));
		}
		$package->createPackage();

		return $package;
	}

	function depositPackage($hookName, $params) {
		$form =& $params[0];
		$context = $form->context;
		$contextId = $context->getId();
        $submission = $form->submission;
        $galleys = $submission->getGalleys();
		$galleysFiles = array();
		$galleysFilesGenres = array();		

		foreach ($galleys as $galley){
			$galleysFiles[] = $galley->getFile();
			$galleysFilesGenres[] = $galley->getFile()->getGenreId();
		}

		if (in_array(DATASET_GENRE_ID, $galleysFilesGenres)) {
			$package = $this->createPackage($submission, $galleysFiles);

			$apiToken = $this->getSetting($contextId, 'apiToken');
			$dataverseUrl = $this->getSetting($contextId, 'dataverse');
			$dataverseServer = $this->getSetting($contextId, 'dataverseServer');	
			$client = new DataverseClient($apiToken, $dataverseServer, $dataverseUrl);

			$editMediaIri = $client->depositAtomEntry($package->getAtomEntryPath());
			if(isset($editMediaIri)) {
				$depositStatus = $client->depositFiles(
					$editMediaIri,
					$package->getPackageFilePath(),
					$package->getPackaging(),
					$package->getContentType()
				);

				if($depositStatus) return true;
			}
		}
	}

}

?>
