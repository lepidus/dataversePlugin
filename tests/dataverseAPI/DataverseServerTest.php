<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.entities.DataverseCredentials');
import('plugins.generic.dataverse.classes.dataverseAPI.DataverseServer');

class DataverseServerTest extends PKPTestCase
{
    private $contextId = 9090;

    private $credentials;

    private $server;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerMockDataverseCredentialsDAO();
        $this->server = new DataverseServer($this->contextId);
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

    public function testReturnsCorrectCrendentialsOfServer(): void
    {
        $credentials = $this->server->getCredentials();

        $this->assertEquals($this->credentials, $credentials);
    }

    public function testReturnsServerUrlOfServer(): void
    {
        $expectedServerUrl = 'https://demo.dataverse.org';
        $serverUrl = $this->server->getDataverseServerUrl();

        $this->assertEquals($expectedServerUrl, $serverUrl);
    }

    public function testReturnsCollectionOfServer(): void
    {
        $expectedCollection = 'example';
        $collection = $this->server->getDataverseCollection();

        $this->assertEquals($expectedCollection, $collection);
    }
}
