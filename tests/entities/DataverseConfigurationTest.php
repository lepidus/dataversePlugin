<?php

use PKP\tests\PKPTestCase;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DefaultAdditionalInstructions;

class DataverseConfigurationTest extends PKPTestCase
{
    private $dataverseConfiguration;
    private $dataverseUrl = 'https://demo.dataverse.org/dataverse/exampleRepository';
    private $apiToken = 'randomToken';
    private $termsOfUse = [
        'en' => 'https://test.dataverse.org/terms-of-use/en',
        'es' => 'https://test.dataverse.org/terms-of-use/es',
        'pt_BR' => 'https://test.dataverse.org/terms-of-use/pt_BR'
    ];
    private $additionalInstructions = [
        'en' => '<p>Additional instructions about research data submission<\/p>',
        'es' => '<p>Instrucciones adicionales para la presentación de datos de pesquisa<\/p>',
        'pt_BR' => '<p>Instruções adicionais sobre submissão de dados de pesquisa<\/p>'
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->dataverseConfiguration = $this->createTestDataverseConfiguration();
    }

    private function createTestDataverseConfiguration(): DataverseConfiguration
    {
        $configuration = new DataverseConfiguration();
        $configuration->setData('dataverseUrl', $this->dataverseUrl);
        $configuration->setData('apiToken', $this->apiToken);
        $configuration->setData('termsOfUse', $this->termsOfUse);
        $configuration->setData('additionalInstructions', $this->additionalInstructions);

        return $configuration;
    }

    public function testGetDataverseServerUrl(): void
    {
        $expectedServerUrl = 'https://demo.dataverse.org';
        $this->assertEquals($expectedServerUrl, $this->dataverseConfiguration->getDataverseServerUrl());
    }

    public function testGetDataverseCollection(): void
    {
        $expectedCollection = 'exampleRepository';
        $this->assertEquals($expectedCollection, $this->dataverseConfiguration->getDataverseCollection());
    }

    public function testGetDefaultAdditionalInstructions(): void
    {
        $this->assertFalse($this->dataverseConfiguration->additionalInstructionsAreEmpty());
        $this->assertEquals($this->additionalInstructions, $this->dataverseConfiguration->getAdditionalInstructions());
        $this->assertEquals($this->additionalInstructions['en'], $this->dataverseConfiguration->getLocalizedAdditionalInstructions());

        $defaultAdditionalInstructions = (new DefaultAdditionalInstructions())->getDefaultInstructions();

        $this->dataverseConfiguration->setData('additionalInstructions', null);
        $this->assertTrue($this->dataverseConfiguration->additionalInstructionsAreEmpty());
        $this->assertEquals($defaultAdditionalInstructions, $this->dataverseConfiguration->getAdditionalInstructions());
        $this->assertEquals($defaultAdditionalInstructions['en'], $this->dataverseConfiguration->getLocalizedAdditionalInstructions());

        $this->dataverseConfiguration->setData('additionalInstructions', ['en' => '', 'es' => '', 'pt_BR' => '']);
        $this->assertTrue($this->dataverseConfiguration->additionalInstructionsAreEmpty());
        $this->assertEquals($defaultAdditionalInstructions, $this->dataverseConfiguration->getAdditionalInstructions());
        $this->assertEquals($defaultAdditionalInstructions['en'], $this->dataverseConfiguration->getLocalizedAdditionalInstructions());
    }
}
