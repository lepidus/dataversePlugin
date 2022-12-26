<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.creators.DataverseServiceFactory');
import('plugins.generic.dataverse.classes.DataverseConfiguration');
import('plugins.generic.dataverse.DataversePlugin');

class DataverseServiceFactoryTest extends PKPTestCase
{
    public function testServiceHasConfiguration(): void
    {
        $dataverseUrl = "https://demo.dataverse.org/dataverse/dataverseDeExemplo/";
        $apiToken = "APIToken";

        $factory = new DataverseServiceFactory();
        $service = $factory->build(new DataverseConfiguration($dataverseUrl, $apiToken), new DataversePlugin());
        $configuration = $service->getClient()->getConfiguration();

        $expectedConfigData = [
            'dataverseUrl' => $dataverseUrl,
            'apiToken' => $apiToken
        ];

        $serviceConfigData = [
            'dataverseUrl' => $configuration->getDataverseUrl(),
            'apiToken' => $configuration->getAPIToken()
        ];

        $this->assertEquals($expectedConfigData, $serviceConfigData);
    }
}
