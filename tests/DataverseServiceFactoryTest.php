<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.creators.DataverseServiceFactory');


class DataverseServiceFactoryTest extends PKPTestCase
{
    public function testFactoryReturnsADataverseService(): void
    {
        $apiToken = "APIToken";
        $dataverseServer = "https://demo.dataverse.org";
        $dataverseUrl = "https://demo.dataverse.org/dataverse/dataverseDeExemplo/";

        $factory = new DataverseServiceFactory();
        $service = $factory->build(new DataverseConfiguration($apiToken, $dataverseServer, $dataverseUrl));
        $this->assertTrue($service instanceof DataverseService);
    }
}