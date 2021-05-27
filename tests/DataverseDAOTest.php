<?php
import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataversePlugin.classes.DataverseDAO');
import('lib.pkp.classes.db.DAO');

class DataverseDAOTest extends PKPTestCase {

    private $contextId;
    private $dataverseServer;
    private $dataverseIntent;
    private $apiToken;
    private $dataverseDAO;

    protected function setUp() : void {
        
        $this->contextId = 1;
        $this->dataverseServer = 'https://demo.dataverse.org';
        $this->dataverseIntent = 'https://demo.dataverse.org/dataverse/dataverseDeExemplo';
        $this->apiToken = 'randomToken';
        $this->dataverseDAO =  new DataverseDAO();

        parent::setUp();
    }

    public function testCredentialsAddedInDB(){

        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $pluginSettingsDao->updateSetting($this->contextId, 'dataverse', 'dataverseServer', $this->dataverseServer);
        $pluginSettingsDao->updateSetting($this->contextId, 'dataverse', 'dataverseIntent', $this->dataverseIntent);
        $pluginSettingsDao->updateSetting($this->contextId, 'dataverse', 'apiToken', $this->apiToken);
        $expectedCredentials = [$this->apiToken, $this->dataverseIntent, $this->dataverseServer];
        $this->assertEquals($expectedCredentials, $this->dataverseDAO->getCredentialsFromDatabase($this->contextId));
    }

    public function testInsertCredentialsOnDatabase(){
        
        $this->dataverseDAO->insertCredentialsOnDatabase($this->contextId, $this->dataverseServer, $this->dataverseIntent, $this->apiToken);
        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $result = $pluginSettingsDao->getPluginSettings($this->contextId, 'dataverse');
        $credentials = [$result['apiToken'], $result['dataverseIntent'] , $result['dataverseServer']];
        $expectedCredentials = [$this->apiToken, $this->dataverseIntent, $this->dataverseServer];
        $this->assertEquals($expectedCredentials, $credentials);
    }

}
?>