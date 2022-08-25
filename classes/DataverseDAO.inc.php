<?php

import('lib.pkp.classes.db.DAO');

class DataverseDAO extends DAO
{
    public function getCredentialsFromDatabase(int $contextId): array
    {
        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $result = $pluginSettingsDao->getPluginSettings($contextId, 'dataverseplugin');
        $credentials = [$result['apiToken'], $result['dataverse']];
        return $credentials;
    }

    public function insertCredentialsOnDatabase(int $contextId, string $dataverseUrl, string $apiToken): bool
    {
        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $pluginSettingsDao->updateSetting($contextId, 'dataverseplugin', 'dataverseUrl', $dataverseUrl);
        $pluginSettingsDao->updateSetting($contextId, 'dataverseplugin', 'apiToken', $apiToken);
        return true;
    }
}
