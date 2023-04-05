<?php

import('lib.pkp.tests.DatabaseTestCase');
import('plugins.generic.dataverse.classes.dataverseCredentials.DataverseCredentialsDAO');

class DataverseCredentialsDAOTest extends DatabaseTestCase
{
    private $contextId = 9090;

    private $pluginName = 'dataverseplugin';

    private $pluginSettingsDAO;

    private $dataverseCredentialsDAO;

    private $dataverseCredentials;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pluginSettingsDAO = DAORegistry::getDAO('PluginSettingsDAO');
        $this->dataverseCredentialsDAO = new DataverseCredentialsDAO();
        $this->dataverseCredentials = $this->createTestDataverseCredentials();
    }

    protected function getAffectedTables(): array
    {
        return array('plugin_settings');
    }

    private function createTestDataverseCredentials(): DataverseCredentials
    {
        $dataverseUrl = 'https://demo.dataverse.org/dataverse/example';
        $apiToken = 'randomToken';
        $termsOfUse = [
            'en_US' => 'https://test.dataverse.org/terms-of-use/en_US',
            'pt_BR' => 'https://test.dataverse.org/terms-of-use/pt_BR',
            'es_ES' => ''
        ];

        $credentials = $this->dataverseCredentialsDAO->newDataObject();
        $credentials->setData('dataverseUrl', $dataverseUrl);
        $credentials->setData('apiToken', $apiToken);
        $credentials->setData('termsOfUse', $termsOfUse);

        return $credentials;
    }

    public function testReturnsCorrectCredentialsFromDatabase(): void
    {
        $settings = $this->dataverseCredentials->getAllData();
        foreach ($settings as $name => $value) {
            $this->pluginSettingsDAO->updateSetting(
                $this->contextId,
                $this->pluginName,
                $name,
                $value,
            );
        }

        $this->assertEquals(
            $this->dataverseCredentials,
            $this->dataverseCredentialsDAO->get($this->contextId)
        );
    }

    public function testDataverseCredentialsWasInsertedInDatabase(): void
    {
        $this->dataverseCredentialsDAO->insert(
            $this->contextId,
            $this->dataverseCredentials
        );

        $this->assertEquals(
            $this->dataverseCredentials,
            $this->dataverseCredentialsDAO->get($this->contextId)
        );
    }
}
