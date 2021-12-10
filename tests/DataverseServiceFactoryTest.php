<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.creators.DataverseServiceFactory');


class DataverseServiceFactoryTest extends PKPTestCase
{
    public function testServiceHasConfiguration(): void
    {
        $apiToken = "APIToken";
        $dataverseServer = "https://demo.dataverse.org";
        $dataverseUrl = "https://demo.dataverse.org/dataverse/dataverseDeExemplo/";

        $factory = new DataverseServiceFactory();
        $service = $factory->build(new DataverseConfiguration($apiToken, $dataverseServer, $dataverseUrl));
        $configuration = $service->getClient()->getConfiguration();

        $expectedConfigData = [
            'apiToken' => $apiToken,
            'dataverseServer' => $dataverseServer,
            'dataverseUrl' => $dataverseUrl
        ];

        $serviceConfigData = [
            'apiToken' => $configuration->getAPIToken(),
            'dataverseServer' => $configuration->getDataverseServer(),
            'dataverseUrl' => $configuration->getDataverseUrl()
        ];

        $this->assertEquals($expectedConfigData, $serviceConfigData);
    }
}