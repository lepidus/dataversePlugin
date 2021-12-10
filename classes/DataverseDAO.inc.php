<?php

import('lib.pkp.classes.db.DAO');

class DataverseDAO extends DAO
{
    public function getCredentialsFromDatabase(int $contextId): array
    {
        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $result = $pluginSettingsDao->getPluginSettings($contextId, 'dataverseplugin');
        $credentials = [$result['apiToken'], $result['dataverse'], $result['dataverseServer']];
        return $credentials;
    }

    public function insertCredentialsOnDatabase(int $contextId, string $dataverseServer, string $dataverse, string $apiToken): bool
    {
        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $pluginSettingsDao->updateSetting($contextId, 'dataverseplugin', 'dataverseServer', $dataverseServer);
        $pluginSettingsDao->updateSetting($contextId, 'dataverseplugin', 'dataverse', $dataverse);
        $pluginSettingsDao->updateSetting($contextId, 'dataverseplugin', 'apiToken', $apiToken);
        return true;
    }
}
