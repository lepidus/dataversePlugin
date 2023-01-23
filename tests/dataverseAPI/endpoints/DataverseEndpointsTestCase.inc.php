<?php

import('lib.pkp.tests.PKPTestCase');

abstract class DataverseEndpointsTestCase extends PKPTestCase
{
    protected $endpoints;

    protected function setUp(): void
    {
        $this->registerMockDataverseCredentialsDAO();
        $contextId = rand();
        $installation = new DataverseInstallation($contextId);
        $this->endpoints = $this->createDataverseEndpoints($installation);

        parent::setUp();
    }

    private function registerMockDataverseCredentialsDAO(): void
    {
        $dataverseCredentialsDAO = $this->getMockBuilder(DataverseCredentialsDAO::class)
            ->setMethods(array('get'))
            ->getMock();

        $credentials = new DataverseCredentials();
        $credentials->setAllData($this->getDataverseCredentialsData());

        $dataverseCredentialsDAO->expects($this->any())
            ->method('get')
            ->will($this->returnValue($credentials));

        DAORegistry::registerDAO('DataverseCredentialsDAO', $dataverseCredentialsDAO);
    }

    abstract protected function getDataverseCredentialsData(): array;

    abstract protected function createDataverseEndpoints(DataverseInstallation $installation): DataverseEndpoints;
}
