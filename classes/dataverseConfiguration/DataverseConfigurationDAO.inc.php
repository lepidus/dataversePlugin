<?php

use Illuminate\Database\Capsule\Manager as Capsule;

import('plugins.generic.dataverse.classes.DataEncryption');
import('plugins.generic.dataverse.classes.dataverseConfiguration.DataverseConfiguration');

class DataverseConfigurationDAO
{
    private $dao;

    private $pluginName = 'dataverseplugin';

    public function __construct()
    {
        $this->dao = DAORegistry::getDAO('PluginSettingsDAO');
    }

    public function newDataObject(): DataverseConfiguration
    {
        return new DataverseConfiguration();
    }

    public function hasConfiguration(int $contextId): bool
    {
        $numSettingsFound = Capsule::table('plugin_settings')
            ->where('plugin_name', 'dataverseplugin')
            ->where('context_id', $contextId)
            ->whereIn('setting_name', ['dataverseUrl', 'apiToken', 'termsOfUse'])
            ->count();
        $minNumSettings = 3;

        return ($numSettingsFound >= $minNumSettings);
    }

    public function get(int $contextId): DataverseConfiguration
    {
        $settings = $this->dao->getPluginSettings($contextId, $this->pluginName);
        $settings = $this->decryptApiToken($settings);
        $configuration = $this->newDataObject();
        $configuration->setAllData($settings);
        return $configuration;
    }

    public function insert(int $contextId, DataverseConfiguration $configuration): void
    {
        $settings = $configuration->getAllData();
        foreach ($settings as $name => $value) {
            $this->dao->updateSetting(
                $contextId,
                $this->pluginName,
                $name,
                $value,
            );
        }
    }

    private function decryptApiToken(array $settings): array
    {
        if (isset($settings['apiToken'])) {
            $encryption = new DataEncryption();
            if ($encryption->textIsEncrypted($settings['apiToken'])) {
                $settings['apiToken'] = $encryption->decryptString($settings['apiToken']);
            }
        }
        return $settings;
    }
}
