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

class DataversePlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);

        $this->loadDispatcherClasses();
        $this->registerDAOClasses();

        return $success;
    }

    private function loadDispatcherClasses(): void
    {
        import('plugins.generic.dataverse.classes.dispatchers.DraftDatasetFilesDispatcher');
        import('plugins.generic.dataverse.classes.dispatchers.DataverseServiceDispatcher');
        import('plugins.generic.dataverse.classes.dispatchers.DatasetSubjectDispatcher');
        import('plugins.generic.dataverse.classes.dispatchers.TemplateDispatcher');

        $DraftDatasetFilesDispatcher = new DraftDatasetFilesDispatcher($this);
        $datasetSubjectDispatcher = new DatasetSubjectDispatcher($this);
        $serviceDispatcher = new DataverseServiceDispatcher($this);
        $templateDispatcher = new TemplateDispatcher($this);
    }

    private function registerDAOClasses(): void
    {
        import('plugins.generic.dataverse.classes.file.DraftDatasetFileDAO');
        import('plugins.generic.dataverse.classes.study.DataverseStudyDAO');
        import('plugins.generic.dataverse.classes.daos.DataverseCredentialsDAO');

        $draftDatasetFileDAO = new DraftDatasetFileDAO();
        $dataverseStudyDAO = new DataverseStudyDAO();
        $dataverseCredentialsDAO = new DataverseCredentialsDAO();

        DAORegistry::registerDAO('DataverseCredentialsDAO', $dataverseCredentialsDAO);
        DAORegistry::registerDAO('DataverseStudyDAO', $dataverseStudyDAO);
        DAORegistry::registerDAO('DraftDatasetFileDAO', $draftDatasetFileDAO);
    }

    public function getDisplayName()
    {
        return __('plugins.generic.dataverse.displayName');
    }

    public function getDescription()
    {
        return __('plugins.generic.dataverse.description');
    }

    public function getPluginFullPath(): string
    {
        $request = Application::get()->getRequest();
        return $request->getBaseUrl() . DIRECTORY_SEPARATOR . $this->getPluginPath();
    }

    public function getActions($request, $actionArgs)
    {
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

    public function manage($args, $request)
    {
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

    public function getInstallMigration(): DataverseMigration
    {
        $this->import('classes.migration.DataverseMigration');
        return new DataverseMigration();
    }
}
