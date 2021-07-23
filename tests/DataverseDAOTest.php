<?php
import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.DataverseDAO');
import('lib.pkp.classes.db.DAO');

class DataverseDAOTest extends PKPTestCase
{
    private $contextId;
    private $dataverseServer;
    private $dataverse;
    private $apiToken;
    private $dataverseDAO;

    protected function setUp(): void
    {
        $this->contextId = 1;
        $this->dataverseServer = 'https://demo.dataverse.org';
        $this->dataverse = 'https://demo.dataverse.org/dataverse/dataverseDeExemplo';
        $this->apiToken = 'randomToken';
        $this->dataverseDAO =  new DataverseDAO();

        parent::setUp();
    }

    public function testCredentialsAddedInDB()
    {
        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $pluginSettingsDao->updateSetting($this->contextId, 'dataverseplugin', 'dataverseServer', $this->dataverseServer);
        $pluginSettingsDao->updateSetting($this->contextId, 'dataverseplugin', 'dataverse', $this->dataverse);
        $pluginSettingsDao->updateSetting($this->contextId, 'dataverseplugin', 'apiToken', $this->apiToken);
        $expectedCredentials = [$this->apiToken, $this->dataverse, $this->dataverseServer];
        $this->assertEquals($expectedCredentials, $this->dataverseDAO->getCredentialsFromDatabase($this->contextId));
    }

    public function testInsertCredentialsOnDatabase()
    {
        $this->dataverseDAO->insertCredentialsOnDatabase($this->contextId, $this->dataverseServer, $this->dataverse, $this->apiToken);
        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $result = $pluginSettingsDao->getPluginSettings($this->contextId, 'dataverseplugin');
        $credentials = [$result['apiToken'], $result['dataverse'] , $result['dataverseServer']];
        $expectedCredentials = [$this->apiToken, $this->dataverse, $this->dataverseServer];
        $this->assertEquals($expectedCredentials, $credentials);
    }
}
