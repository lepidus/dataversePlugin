<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.entities.DataverseCredentials');
import('plugins.generic.dataverse.classes.entities.DataverseServer');
import('plugins.generic.dataverse.classes.factories.DataverseServerFactory');

class DataverseServerFactoryTest extends PKPTestCase
{
    private $contextId = 9090;

    private $credentials;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerMockDataverseCredentialsDAO();
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

    public function testFactoryCreatesDataverseServer(): void
    {
        $factory = new DataverseServerFactory();
        $server = $factory->createDataverseServer($this->contextId);
        $this->assertInstanceOf(DataverseServer::class, $server);
    }

    public function testFactoryCreatesDataverseServerWithCorrectCredentials(): void
    {
        $factory = new DataverseServerFactory();
        $server = $factory->createDataverseServer($this->contextId);
        $this->assertEquals($this->credentials, $server->getCredentials());
    }

    public function testFactoryCreatesDataverseServerWithCorrectDataverseServerUrl(): void
    {
        $factory = new DataverseServerFactory();
        $server = $factory->createDataverseServer($this->contextId);
        $this->assertEquals('https://demo.dataverse.org', $server->getDataverseServerUrl());
    }

    public function testFactoryCreatesDataverseServerWithCorrectDataverseCollectionAlias(): void
    {
        $factory = new DataverseServerFactory();
        $server = $factory->createDataverseServer($this->contextId);
        $this->assertEquals('example', $server->getDataverseCollection());
    }
}
