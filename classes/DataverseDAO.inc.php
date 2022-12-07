<?php

import('lib.pkp.classes.db.DAO');

class DataverseDAO extends DAO
{
    public function getCredentialsFromDatabase(int $contextId): array
    {
        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $result = $pluginSettingsDao->getPluginSettings($contextId, 'dataverseplugin');
        $credentials = [$result['apiToken'], $result['dataverseUrl'], $result['termsOfUse']];
        return $credentials;
    }

    public function insertCredentialsOnDatabase(int $contextId, string $dataverseUrl, string $apiToken): bool
    {
        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $pluginSettingsDao->updateSetting($contextId, 'dataverseplugin', 'dataverseUrl', $dataverseUrl);
        $pluginSettingsDao->updateSetting($contextId, 'dataverseplugin', 'apiToken', $apiToken);
        return true;
    }

    public function getTermsOfUse(int $contextId, string $locale = null): string
    {
        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $allTermsOfUse = $pluginSettingsDao->getSetting($contextId, 'dataverseplugin', 'termsOfUse');

        $primaryLocale = AppLocale::getPrimaryLocale();
        if (isset($locale)) {
            if (!empty($allTermsOfUse[$locale])) {
                return $allTermsOfUse[$locale];
            }
        }
        return $allTermsOfUse[$primaryLocale];
    }
}
