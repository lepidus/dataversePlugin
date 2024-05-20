<?php
/**
 * @file plugins/generic/dataverse/DataversePlugin.inc.php
 *
 * Copyright (c) 2019 - 2024 Lepidus Tecnologia
 * Copyright (c) 2020 - 2024 SciELO
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class DataversePlugin
 * @ingroup plugins_generic_dataverse
 *
 * @brief dataverse plugin class
 */

namespace APP\plugins\generic\dataverse;

use PKP\plugins\GenericPlugin;
use APP\core\Application;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use APP\notification\NotificationManager;
use PKP\core\JSONMessage;
use PKP\db\DAORegistry;
use APP\plugins\generic\dataverse\classes\migrations\DataverseMigration;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfigurationDAO;
use APP\plugins\generic\dataverse\DataverseSettingsForm;

class DataversePlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);

        if (Application::isUnderMaintenance()) {
            return true;
        }

        $dataverseConfigurationDAO = new DataverseConfigurationDAO();
        DAORegistry::registerDAO('DataverseConfigurationDAO', $dataverseConfigurationDAO);

        $context = Application::get()->getRequest()->getContext();

        if(!is_null($context) and $dataverseConfigurationDAO->hasConfiguration($context->getId())) {
            $this->loadDispatcherClasses();
            //PluginRegistry::register('reports', $this->getReportPlugin(), $this->getPluginPath());
        }

        return $success;
    }

    private function loadDispatcherClasses(): void
    {
        $remainingDispatcherClasses = [
            'DatasetInformationDispatcher',
            'DatasetReviewDispatcher',
        ];

        $dispatcherClasses = [
            'DataStatementDispatcher',
            'DataStatementTabDispatcher',
            'DraftDatasetFilesDispatcher',
            'DatasetMetadataDispatcher',
            'DatasetTabDispatcher',
            'DataverseEventsDispatcher'
        ];

        foreach ($dispatcherClasses as $dispatcherClass) {
            $dispatcherClass = 'APP\plugins\generic\dataverse\classes\dispatchers\\' . $dispatcherClass;
            $dispatcher = new $dispatcherClass($this);
        }
    }

    public function getDisplayName()
    {
        return __('plugins.generic.dataverse.displayName');
    }

    public function getDescription()
    {
        return __('plugins.generic.dataverse.description');
    }

    public function getReportPlugin()
    {
        $this->import('report.DataverseReportPlugin');
        return new DataverseReportPlugin();
    }

    public function getInstallEmailTemplatesFile()
    {
        return $this->getPluginPath() . DIRECTORY_SEPARATOR . 'emailTemplates.xml';
    }

    public function getPluginFullPath(): string
    {
        $request = Application::get()->getRequest();
        return $request->getBaseUrl() . DIRECTORY_SEPARATOR . $this->getPluginPath();
    }

    public function getActions($request, $actionArgs)
    {
        $router = $request->getRouter();
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

                $form = new DataverseSettingsForm($this, $contextId);
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
                    $form->display($request);
                }

                return new JSONMessage(true, $form->fetch($request));
        }
        return parent::manage($args, $request);
    }

    public function getInstallMigration(): DataverseMigration
    {
        return new DataverseMigration();
    }
}
