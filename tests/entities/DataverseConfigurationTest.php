<?php

use APP\core\Application;
use APP\core\PageRouter;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DefaultAdditionalInstructions;
use PKP\core\Dispatcher;
use PKP\core\Registry;
use PKP\tests\PKPTestCase;

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

    protected function getMockedRegistryKeys(): array
    {
        return ['request'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupRequest();
        $this->dataverseConfiguration = $this->createTestDataverseConfiguration();
    }

    private function setupRequest(): void
    {
        Registry::delete('request');
        $application = Application::get();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['PATH_INFO'] = 'index/test-page/test-op';
        $request = $application->getRequest();

        $router = new PageRouter();
        $router->setApplication($application);
        $dispatcher = new Dispatcher();
        $dispatcher->setApplication($application);
        $router->setDispatcher($dispatcher);
        $request->setRouter($router);

        Registry::set('request', $request);
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

    /**
     * @dataProvider localDataverseUrls
     */
    public function testGetLocalDataverseServerUrl(string $url, string $expectedServerUrl): void
    {
        $this->dataverseConfiguration->setDataverseUrl($url);

        $this->assertSame($expectedServerUrl, $this->dataverseConfiguration->getDataverseServerUrl());
        $this->assertSame('exampleRepository', $this->dataverseConfiguration->getDataverseCollection());
        $this->assertTrue((new \PKP\validation\ValidatorUrl())->isValid($url));
    }

    public function localDataverseUrls(): array
    {
        return [
            ['http://localhost/dataverse/exampleRepository', 'http://localhost'],
            ['http://localhost:8080/dataverse/exampleRepository', 'http://localhost:8080'],
            ['http://127.0.0.1:8080/dataverse/exampleRepository', 'http://127.0.0.1:8080'],
            ['https://localhost:8443/dataverse/exampleRepository', 'https://localhost:8443'],
            ['https://127.0.0.1/dataverse/exampleRepository', 'https://127.0.0.1'],
        ];
    }

    /**
     * @dataProvider invalidDataverseServerUrls
     */
    public function testRejectsInvalidDataverseServerUrl(string $url): void
    {
        $this->dataverseConfiguration->setDataverseUrl($url);

        $this->expectException(\InvalidArgumentException::class);

        $this->dataverseConfiguration->getDataverseServerUrl();
    }

    public function invalidDataverseServerUrls(): array
    {
        return [
            ['http://demo.dataverse.org/dataverse/exampleRepository'],
            ['http://localhost.example.org/dataverse/exampleRepository'],
            ['http://localhost:99999/dataverse/exampleRepository'],
            ['ftp://localhost/dataverse/exampleRepository'],
            ['invalid'],
        ];
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
