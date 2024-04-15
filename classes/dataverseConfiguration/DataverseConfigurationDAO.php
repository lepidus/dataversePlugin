<?php

namespace APP\plugins\generic\dataverse\classes\dataverseConfiguration;

use Illuminate\Support\Facades\DB;
use PKP\db\DAORegistry;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;

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
        $numSettingsFound = DB::table('plugin_settings')
            ->where('plugin_name', 'dataverseplugin')
            ->whereIn('setting_name', ['dataverseUrl', 'apiToken', 'termsOfUse'])
            ->count();
        $minNumSettings = 3;

        return ($numSettingsFound >= $minNumSettings);
    }

    public function get(int $contextId): DataverseConfiguration
    {
        $settings = $this->dao->getPluginSettings($contextId, $this->pluginName);
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
}
