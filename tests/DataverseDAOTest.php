<?php
import('lib.pkp.tests.DatabaseTestCase');
import('plugins.generic.dataverse.classes.DataverseDAO');
import('lib.pkp.classes.db.DAO');

class DataverseDAOTest extends DatabaseTestCase
{
    private $contextId;
    private $dataverseUrl;
    private $apiToken;
    private $dataverseDAO;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contextId = 1;
        $this->dataverseUrl = 'https://demo.dataverse.org/dataverse/dataverseDeExemplo';
        $this->apiToken = 'randomToken';
        $this->dataverseDAO =  new DataverseDAO();
    }

    protected function getEffectedTables(): array
    {
        return ['plugin_settings'];
    }

    public function testCredentialsAddedInDB(): void
    {
        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $pluginSettingsDao->updateSetting($this->contextId, 'dataverseplugin', 'dataverse', $this->dataverseUrl);
        $pluginSettingsDao->updateSetting($this->contextId, 'dataverseplugin', 'apiToken', $this->apiToken);
        $expectedCredentials = [$this->apiToken, $this->dataverseUrl];
        $this->assertEquals($expectedCredentials, $this->dataverseDAO->getCredentialsFromDatabase($this->contextId));
    }

    public function testInsertCredentialsOnDatabase(): void
    {
        $this->dataverseDAO->insertCredentialsOnDatabase($this->contextId, $this->dataverseUrl, $this->apiToken);
        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $result = $pluginSettingsDao->getPluginSettings($this->contextId, 'dataverseplugin');
        $credentials = [$result['apiToken'], $result['dataverseUrl']];
        $expectedCredentials = [$this->apiToken, $this->dataverseUrl];
        $this->assertEquals($expectedCredentials, $credentials);
    }
}
