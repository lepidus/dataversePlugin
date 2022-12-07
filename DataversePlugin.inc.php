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

class DataversePlugin extends GenericPlugin {

	/**
	 * @see LazyLoadPlugin::register()
	 */
	public function register($category, $path, $mainContextId = NULL) {
		$success = parent::register($category, $path, $mainContextId);
		
		$this->importDispatcherClasses();
		$this->registerDAOClasses();

		return $success;
	}

	private function importDispatcherClasses(): void
	{
		import('plugins.generic.dataverse.classes.dispatchers.TemplateDispatcher');
		$templateDispatcher = new TemplateDispatcher($this);

		import('plugins.generic.dataverse.classes.dispatchers.DataverseServiceDispatcher');
		$serviceDispatcher = new DataverseServiceDispatcher($this);

		import('plugins.generic.dataverse.classes.dispatchers.DatasetSubjectDispatcher');
		$datasetSubjectDispatcher = new DatasetSubjectDispatcher($this);
	}

	private function registerDAOClasses(): void
	{
		import('plugins.generic.dataverse.classes.DataverseDAO');
		$dataverseDAO = new DataverseDAO();
		DAORegistry::registerDAO('DataverseDAO', $dataverseDAO);
		
		import('plugins.generic.dataverse.classes.study.DataverseStudyDAO');
		$dataverseStudyDAO = new DataverseStudyDAO();
		DAORegistry::registerDAO('DataverseStudyDAO', $dataverseStudyDAO);

		import('plugins.generic.dataverse.classes.file.DraftDatasetFileDAO');
		$draftDatasetFileDAO = new DraftDatasetFileDAO;
		DAORegistry::registerDAO('DraftDatasetFileDAO', $draftDatasetFileDAO);
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

				$this->import('classes.form.DataverseConfigurationForm');
				$form = new DataverseConfigurationForm($this, $contextId);
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

	function getInstallMigration(): DataverseMigration {
        $this->import('classes.migration.DataverseMigration');
        return new DataverseMigration();
    }
}

?>
