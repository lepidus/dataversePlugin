<?php 

import('lib.pkp.classes.db.DAO');

class DataverseDAO extends DAO {
    
    public function getCredentialsFromDatabase($contextId){
        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $result = $pluginSettingsDao->getPluginSettings($contextId, 'dataverse');
        $credentials = [$result['apiToken'], $result['dvnUri']];
        return $credentials;
    }

    public function insertCredentialsOnDatabase($contextId, $dvnUri, $apiToken){
        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $pluginSettingsDao->updateSetting($contextId, 'dataverse', 'dvnUri', $dvnUri);
        $pluginSettingsDao->updateSetting($contextId, 'dataverse', 'apiToken', $apiToken);
        return true;
    }
}
?>