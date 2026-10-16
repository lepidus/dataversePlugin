<?php

namespace APP\plugins\generic\dataverse\classes\dataverseConfiguration;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\MissingAppKeyException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PKP\db\DAORegistry;

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
        $settings = $this->encryptApiToken($configuration->getAllData());
        foreach ($settings as $name => $value) {
            $this->dao->updateSetting(
                $contextId,
                $this->pluginName,
                $name,
                $value,
            );
        }
    }

    private function encryptApiToken(array $settings): array
    {
        if (!empty($settings['apiToken'])) {
            $settings['apiToken'] = Crypt::encryptString($settings['apiToken']);
        }
        return $settings;
    }

    private function decryptApiToken(array $settings): array
    {
        if (empty($settings['apiToken'])) {
            return $settings;
        }

        try {
            $settings['apiToken'] = Crypt::decryptString($settings['apiToken']);
        } catch (DecryptException | MissingAppKeyException $exception) {
            error_log('Dataverse: the stored API token could not be decrypted and must be entered again in the plugin settings.');
            $settings['apiToken'] = '';
        }
        return $settings;
    }
}
