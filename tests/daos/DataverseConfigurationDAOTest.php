<?php

use PKP\tests\DatabaseTestCase;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfigurationDAO;

class DataverseConfigurationDAOTest extends DatabaseTestCase
{
    private $contextId = 9090;
    private $pluginName = 'dataverseplugin';
    private $pluginSettingsDAO;
    private $dataverseConfigurationDAO;
    private $dataverseConfiguration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pluginSettingsDAO = DAORegistry::getDAO('PluginSettingsDAO');
        $this->dataverseConfigurationDAO = new DataverseConfigurationDAO();
        $this->dataverseConfiguration = $this->createTestDataverseConfiguration();
    }

    protected function getAffectedTables(): array
    {
        return ['plugin_settings'];
    }

    private function createTestDataverseConfiguration(): DataverseConfiguration
    {
        $dataverseUrl = 'https://demo.dataverse.org/dataverse/example';
        $apiToken = 'randomToken';
        $termsOfUse = [
            'en' => 'https://test.dataverse.org/terms-of-use/en',
            'pt_BR' => 'https://test.dataverse.org/terms-of-use/pt_BR',
            'es' => ''
        ];

        $configuration = $this->dataverseConfigurationDAO->newDataObject();
        $configuration->setData('dataverseUrl', $dataverseUrl);
        $configuration->setData('apiToken', $apiToken);
        $configuration->setData('termsOfUse', $termsOfUse);

        return $configuration;
    }

    public function testContextHasConfiguration(): void
    {
        $this->assertFalse($this->dataverseConfigurationDAO->hasConfiguration($this->contextId));

        $this->dataverseConfigurationDAO->insert($this->contextId, $this->dataverseConfiguration);

        $this->assertTrue($this->dataverseConfigurationDAO->hasConfiguration($this->contextId));
    }

    public function testReturnsCorrectConfigurationFromDatabase(): void
    {
        $settings = $this->dataverseConfiguration->getAllData();
        foreach ($settings as $name => $value) {
            $this->pluginSettingsDAO->updateSetting(
                $this->contextId,
                $this->pluginName,
                $name,
                $value,
            );
        }

        $this->assertEquals(
            $this->dataverseConfiguration,
            $this->dataverseConfigurationDAO->get($this->contextId)
        );
    }

    public function testDataverseConfigurationWasInsertedInDatabase(): void
    {
        $this->dataverseConfigurationDAO->insert(
            $this->contextId,
            $this->dataverseConfiguration
        );

        $this->assertEquals(
            $this->dataverseConfiguration,
            $this->dataverseConfigurationDAO->get($this->contextId)
        );
    }
}
