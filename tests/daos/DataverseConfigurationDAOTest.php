<?php

import('lib.pkp.tests.DatabaseTestCase');
import('plugins.generic.dataverse.classes.dataverseConfiguration.DataverseConfigurationDAO');

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
        return array('plugin_settings');
    }

    private function createTestDataverseConfiguration(): DataverseConfiguration
    {
        $dataverseUrl = 'https://demo.dataverse.org/dataverse/example';
        $apiToken = 'randomToken';
        $termsOfUse = [
            'en_US' => 'https://test.dataverse.org/terms-of-use/en_US',
            'pt_BR' => 'https://test.dataverse.org/terms-of-use/pt_BR',
            'es_ES' => ''
        ];

        $configuration = $this->dataverseConfigurationDAO->newDataObject();
        $configuration->setData('dataverseUrl', $dataverseUrl);
        $configuration->setData('apiToken', $apiToken);
        $configuration->setData('termsOfUse', $termsOfUse);

        return $configuration;
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
