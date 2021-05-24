<?php 

import('lib.pkp.classes.db.DAO');

class DataverseDAO extends DAO {
    
    public function getCredentialsFromDatabase($contextId){
        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $result = $pluginSettingsDao->getPluginSettings($contextId, 'dataverse');
        $credentials = [$result['apiToken'], $result['dataverseServer']];
        return $credentials;
    }

    public function insertCredentialsOnDatabase($contextId, $dataverseServer, $apiToken){
        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $pluginSettingsDao->updateSetting($contextId, 'dataverse', 'dataverseServer', $dataverseServer);
        $pluginSettingsDao->updateSetting($contextId, 'dataverse', 'apiToken', $apiToken);
        return true;
    }
}
?>