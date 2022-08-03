<?php
import('lib.pkp.tests.DatabaseTestCase');
import('plugins.generic.dataverse.classes.DataverseDAO');
import('lib.pkp.classes.db.DAO');

class DataverseDAOTest extends DatabaseTestCase
{
    private $contextsId;
    private $dataverseUrl;
    private $apiToken;
    private $dataverseDAO;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contextsId = $this->retrieveContextIds();
        $this->dataverseUrl = 'https://demo.dataverse.org/dataverse/dataverseDeExemplo';
        $this->apiToken = 'randomToken';
        $this->dataverseDAO =  new DataverseDAO();
    }

    public function retrieveContextIds(): array
    {
        $contextIds = array();
        $contexts = Application::getContextDAO()->getAll();
        while ($context = $contexts->next()) {
            array_push($contextIds, $context->getId());
        }
        return $contextIds;
    }


    protected function getAffectedTables(): array
    {
        return array('plugin_settings');
    }

    public function testCredentialsAddedInDB(): void
    {
        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $contextId = $this->contextsId[0];
        $pluginSettingsDao->updateSetting($contextId, 'dataverseplugin', 'dataverse', $this->dataverseUrl);
        $pluginSettingsDao->updateSetting($contextId, 'dataverseplugin', 'apiToken', $this->apiToken);
        $expectedCredentials = [$this->apiToken, $this->dataverseUrl];
        $this->assertEquals($expectedCredentials, $this->dataverseDAO->getCredentialsFromDatabase($contextId));
    }

    public function testInsertCredentialsOnDatabase(): void
    {
        $contextId = $this->contextsId[0];
        $this->dataverseDAO->insertCredentialsOnDatabase($contextId, $this->dataverseUrl, $this->apiToken);
        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $result = $pluginSettingsDao->getPluginSettings($contextId, 'dataverseplugin');
        $credentials = [$result['apiToken'], $result['dataverseUrl']];
        $expectedCredentials = [$this->apiToken, $this->dataverseUrl];
        $this->assertEquals($expectedCredentials, $credentials);
    }
}
