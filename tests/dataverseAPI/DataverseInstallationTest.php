<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.entities.DataverseCredentials');
import('plugins.generic.dataverse.classes.dataverseAPI.DataverseInstallation');

class DataverseInstallationTest extends PKPTestCase
{
    private $contextId = 9090;

    private $credentials;

    private $installation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerMockDataverseCredentialsDAO();
        $this->installation = new DataverseInstallation($this->contextId);
    }

    private function registerMockDataverseCredentialsDAO(): void
    {
        $dataverseCredentialsDAO = $this->getMockBuilder(DataverseCredentialsDAO::class)
            ->setMethods(array('get'))
            ->getMock();

        $this->credentials = new DataverseCredentials();
        $this->credentials->setDataverseUrl('https://demo.dataverse.org/dataverse/example');
        $this->credentials->setAPIToken('randomToken');
        $this->credentials->setTermsOfUse(array('en_US' => 'https://demo.dataverse.org/terms-of-use'));

        $dataverseCredentialsDAO->expects($this->any())
            ->method('get')
            ->will($this->returnValue($this->credentials));

        DAORegistry::registerDAO('DataverseCredentialsDAO', $dataverseCredentialsDAO);
    }

    public function testReturnsCorrectCrendentialsOfInstallation(): void
    {
        $credentials = $this->installation->getCredentials();

        $this->assertEquals($this->credentials, $credentials);
    }

    public function testReturnsServerUrlOfInstallation(): void
    {
        $expectedServerUrl = 'https://demo.dataverse.org';
        $serverUrl = $this->installation->getDataverseServerUrl();

        $this->assertEquals($expectedServerUrl, $serverUrl);
    }

    public function testReturnsCollectionOfInstallation(): void
    {
        $expectedCollection = 'example';
        $collection = $this->installation->getDataverseCollection();

        $this->assertEquals($expectedCollection, $collection);
    }
}
