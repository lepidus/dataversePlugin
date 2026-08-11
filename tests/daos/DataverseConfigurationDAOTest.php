<?php

use PKP\tests\DatabaseTestCase;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfigurationDAO;
use APP\plugins\generic\dataverse\tests\helpers\CreatesTestContext;

class DataverseConfigurationDAOTest extends DatabaseTestCase
{
    use CreatesTestContext;

    private $context;
    private $pluginName = 'dataverseplugin';
    private $pluginSettingsDAO;
    private $dataverseConfigurationDAO;
    private $dataverseConfiguration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = $this->createTestContext();
        $this->pluginSettingsDAO = DAORegistry::getDAO('PluginSettingsDAO');
        $this->dataverseConfigurationDAO = new DataverseConfigurationDAO();
        $this->dataverseConfiguration = $this->createTestDataverseConfiguration();
    }

    protected function tearDown(): void
    {
        $this->deleteTestContext($this->context);

        parent::tearDown();
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
        $additionalInstructions = [
            'en' => '<p>Additional instructions about research data submission<\/p>',
            'pt_BR' => '<p>Instruções adicionais sobre submissão de dados de pesquisa<\/p>',
            'es' => '<p>Instrucciones adicionales para la presentación de datos de pesquisa<\/p>'
        ];

        $configuration = $this->dataverseConfigurationDAO->newDataObject();
        $configuration->setData('dataverseUrl', $dataverseUrl);
        $configuration->setData('apiToken', $apiToken);
        $configuration->setData('termsOfUse', $termsOfUse);
        $configuration->setData('additionalInstructions', $additionalInstructions);

        return $configuration;
    }

    public function testContextHasConfiguration(): void
    {
        $this->assertFalse($this->dataverseConfigurationDAO->hasConfiguration($this->context->getId()));

        $this->dataverseConfigurationDAO->insert($this->context->getId(), $this->dataverseConfiguration);

        $this->assertTrue($this->dataverseConfigurationDAO->hasConfiguration($this->context->getId()));
    }

    public function testReturnsCorrectConfigurationFromDatabase(): void
    {
        $settings = $this->dataverseConfiguration->getAllData();
        foreach ($settings as $name => $value) {
            $this->pluginSettingsDAO->updateSetting(
                $this->context->getId(),
                $this->pluginName,
                $name,
                $value,
            );
        }

        $this->assertEquals(
            $this->dataverseConfiguration,
            $this->dataverseConfigurationDAO->get($this->context->getId())
        );
    }

    public function testDataverseConfigurationWasInsertedInDatabase(): void
    {
        $this->dataverseConfigurationDAO->insert(
            $this->context->getId(),
            $this->dataverseConfiguration
        );

        $this->assertEquals(
            $this->dataverseConfiguration,
            $this->dataverseConfigurationDAO->get($this->context->getId())
        );
    }
}
