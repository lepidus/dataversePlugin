<?php
import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.DataverseDAO');
import('lib.pkp.classes.db.DAO');

class DataverseDAOTest extends PKPTestCase {

    private $contextId;
    private $dvnUri;
    private $apiToken;
    private $dataverseDAO;

    protected function setUp() : void {
        
        $this->contextId = 1;
        $this->dvnUri = 'https://demo.dataverse.org';
        $this->apiToken = 'randomToken';
        $this->dataverseDAO =  new DataverseDAO();

        parent::setUp();
    }

    public function testCredentialsAddedInDB(){

        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $pluginSettingsDao->updateSetting($this->contextId, 'dataverse', 'dvnUri', $this->dvnUri);
        $pluginSettingsDao->updateSetting($this->contextId, 'dataverse', 'apiToken', $this->apiToken);
        $expectedCredentials = [$this->apiToken, $this->dvnUri];
        $this->assertEquals($expectedCredentials, $this->dataverseDAO->getCredentialsFromDatabase($this->contextId));
    }

    public function testInsertCredentialsOnDatabase(){
        
        $this->dataverseDAO->insertCredentialsOnDatabase($this->contextId, $this->dvnUri, $this->apiToken);
        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $result = $pluginSettingsDao->getPluginSettings($this->contextId, 'dataverse');
        $credentials = [$result['apiToken'], $result['dvnUri']];
        $expectedCredentials = [$this->apiToken, $this->dvnUri];
        $this->assertEquals($expectedCredentials, $credentials);
    }

}
?>