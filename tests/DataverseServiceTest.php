<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.api.DataverseService');
import('plugins.generic.dataverse.classes.DataverseConfiguration');

class DatasetServiceTest extends PKPTestCase {

    function createDataverseClientMock(): DataverseClient
    {
        $dataverseUrl = "https://demo.dataverse.org/dataverse/dataverseDeExemplo/";
        $apiToken = "apiRandom";

        $mockClient = $this->getMockBuilder(DataverseClient::class)
            ->setConstructorArgs([
                new DataverseConfiguration(
                    $dataverseUrl,
                    $apiToken
            )])
            ->setMethods(array('retrieveDepositReceipt'))
            ->getMock();

        $sacNewStatus = 200;
        $sacTheXml = '<sac_title>Dataverse de Exemplo Lepidus</sac_title>';
        $swordAppEntry = new SWORDAPPEntry($sacNewStatus, $sacTheXml);
        $swordAppEntry->sac_title = 'Dataverse de Exemplo Lepidus';

        $mockClient->expects($this->any())
            ->method('retrieveDepositReceipt')
            ->will(
                $this->returnValue($swordAppEntry));

        return $mockClient;
    }

    function testReturnDataverseNameLikeDataverseDeExemploLepidus(): void
    {
        $client = $this->createDataverseClientMock();

        $service = new DataverseService($client);
        $dataverseName = $service->getDataverseName();

        $this->assertEquals('Dataverse de Exemplo Lepidus', $dataverseName);
    }

}

?>