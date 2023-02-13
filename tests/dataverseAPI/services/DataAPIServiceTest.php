<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.dataverseAPI.services.DataAPIService');

class DataAPIServiceTest extends PKPTestCase
{
    private const SUCCESS = 200;

    private const FAIL = 400;

    private function getDataClientMock(int $responseState): IDataAPIClient
    {
        $response = $this->getDataClientResponse($responseState);

        $clientMock = $this->getMockBuilder(IDataAPIClient::class)
            ->setMethods(array('getDataverseData'))
            ->getMock();

        $clientMock->expects($this->any())
            ->method('getDataverseData')
            ->will($this->returnValue($response));

        return $clientMock;
    }

    private function getDataClientResponse(int $responseState): DataverseResponse
    {
        $statusCode = $responseState;

        if ($responseState == self::SUCCESS) {
            $message = 'OK';
            $data = file_get_contents(__DIR__ . '/../../assets/nativeAPICollectionResponseExample.json');
        } else {
            $message = 'Error Processing Request';
            $data = null;
        }

        return new DataverseResponse($statusCode, $message, $data);
    }

    public function testReturnsDataverseServerNameWhenAPIRequestIsSuccessful(): void
    {
        $client = $this->getDataClientMock(self::SUCCESS);
        $service = new DataAPIService($client);

        $this->assertEquals(
            'Demo Dataverse',
            $service->getDataverseServerName()
        );
    }

    public function testTrownExceptionWhenAPIRequestFail(): void
    {
        $this->expectExceptionCode(self::FAIL);
        $this->expectExceptionMessage('Error Processing Request');

        $client = $this->getDataClientMock(self::FAIL);
        $service = new DataAPIService($client);
        $service->getDataverseServerName();
    }
}
