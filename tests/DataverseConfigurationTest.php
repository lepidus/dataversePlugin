<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.DataverseConfiguration');


class DataverseConfigurationTest extends PKPTestCase
{
    private $apiToken = "APIToken";
    private $dataverseUrl = "https://demo.dataverse.org/dataverse/dataverseDeExemplo/";

    public function testConfigurationHasAPIToken(): void
    {
        $configuration = new DataverseConfiguration($this->dataverseUrl, $this->apiToken);
        $this->assertEquals($this->apiToken, $configuration->getAPIToken());
    }

    public function testConfigurationHasDataverseServer(): void
    {
        $dataverseServer = "https://demo.dataverse.org";
        $configuration = new DataverseConfiguration($this->dataverseUrl, $this->apiToken);
        $this->assertEquals($dataverseServer, $configuration->getDataverseServer());
    }

    public function testConfigurationHasDataverseURL(): void
    {
        $configuration = new DataverseConfiguration($this->dataverseUrl, $this->apiToken);
        $this->assertEquals($this->dataverseUrl, $configuration->getDataverseUrl());
    }

    public function testConfigurationReturnsValidDataverseCollection(): void
    {
        $expectedDataverseCollection = "/dataverse/dataverseDeExemplo/";
        $configuration = new DataverseConfiguration($this->dataverseUrl, $this->apiToken);
        $this->assertEquals($expectedDataverseCollection, $configuration->getDataverseCollection());
    }

    public function testConfigurationReturnsValidDataDepositBaseURL(): void
    {
        $expectedDataverseCollection = "https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/";
        $configuration = new DataverseConfiguration($this->dataverseUrl, $this->apiToken);
        $this->assertEquals($expectedDataverseCollection, $configuration->getDataDepositBaseUrl());
    }

    public function testConfigurationReturnsValidDataverseServiceDocumentURL(): void
    {
        $expectedDepositUrl = "https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/service-document";
        $configuration = new DataverseConfiguration($this->dataverseUrl, $this->apiToken);
        $this->assertEquals($expectedDepositUrl, $configuration->getDataverseServiceDocumentUrl());
    }

    public function testConfigurationReturnsValidDataverseDepositURL(): void
    {
        $expectedDepositUrl = "https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/collection/dataverse/dataverseDeExemplo/";
        $configuration = new DataverseConfiguration($this->dataverseUrl, $this->apiToken);
        $this->assertEquals($expectedDepositUrl, $configuration->getDataverseDepositUrl());
    }

    public function testConfigurationReturnsValidDataverseReleaseURL(): void
    {
        $expectedDepositUrl = "https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit/dataverse/dataverseDeExemplo/";
        $configuration = new DataverseConfiguration($this->dataverseUrl, $this->apiToken);
        $this->assertEquals($expectedDepositUrl, $configuration->getDataverseReleaseUrl());
    }
}
