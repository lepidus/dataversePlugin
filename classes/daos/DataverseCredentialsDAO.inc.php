<?php

import('plugins.generic.dataverse.classes.entities.DataverseCredentials');

class DataverseCredentialsDAO
{
    private $dao;

    private $pluginName = 'dataverseplugin';

    public function __construct()
    {
        $this->dao = DAORegistry::getDAO('PluginSettingsDAO');
    }

    public function newDataObject(): DataverseCredentials
    {
        return new DataverseCredentials();
    }

    public function get(int $contextId): DataverseCredentials
    {
        $settings = $this->dao->getPluginSettings($contextId, $this->pluginName);
        $credentials = $this->newDataObject();
        $credentials->setAllData($settings);
        return $credentials;
    }

    public function insert(int $contextId, DataverseCredentials $credentials): void
    {
        $settings = $credentials->getAllData();
        foreach ($settings as $name => $value) {
            $this->dao->updateSetting(
                $contextId,
                $this->pluginName,
                $name,
                $value,
            );
        }
    }
}
