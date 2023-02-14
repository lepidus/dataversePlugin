<?php

import('lib.pkp.tests.PKPTestCase');

abstract class DataverseEndpointsTestCase extends PKPTestCase
{
    protected $endpoints;

    protected function setUp(): void
    {
        $server = new DataverseServer($this->getDataverseCredentialsData(), 'https://demo.dataverse.org', 'example');
        $this->endpoints = $this->createDataverseEndpoints($server);

        parent::setUp();
    }

    protected function getDataverseCredentialsData(): DataverseCredentials
    {
        $credentials = new DataverseCredentials();
        $credentials->setDataverseUrl('https://demo.dataverse.org/dataverse/example');
        $credentials->setAPIToken('randomToken');
        $credentials->setTermsOfUse(array('en_US' => 'https://demo.dataverse.org/terms-of-use'));
        return $credentials;
    }

    abstract protected function createDataverseEndpoints(DataverseServer $server): DataverseEndpoints;
}
