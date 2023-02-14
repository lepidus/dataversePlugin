<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.dataverseAPI.services.DataAPIService');

class DataAPIServiceTest extends PKPTestCase
{
    private const SUCCESS = 200;

    private const FAIL = 400;

    private function getDataAPIClientMock(string $method, int $responseState, string $data = null): IDataAPIClient
    {
        $response = $this->getDataAPIClientResponse($responseState, $data);

        $clientMock = $this->getMockBuilder(IDataAPIClient::class)
            ->setMethods(array($method))
            ->getMock();

        $clientMock->expects($this->any())
            ->method($method)
            ->will($this->returnValue($response));

        return $clientMock;
    }

    private function getDataAPIClientResponse(int $responseState, string $data = null): DataverseResponse
    {
        $statusCode = $responseState;

        if ($responseState == self::SUCCESS) {
            $message = 'OK';
        } else {
            $message = 'Error Processing Request';
        }

        return new DataverseResponse($statusCode, $message, $data);
    }

    public function testReturnsDataverseServerNameWhenAPIRequestIsSuccessful(): void
    {
        $data = json_encode(array(
            'data' => array(
                'name' => 'Demo Dataverse'
            )
        ));

        $client = $this->getDataAPIClientMock('getDataverseServerData', self::SUCCESS, $data);
        $service = new DataAPIService($client);

        $this->assertEquals(
            'Demo Dataverse',
            $service->getDataverseServerName()
        );
    }

    public function testReturnsDataverseCollectionNameWhenAPIRequestIsSuccessful(): void
    {
        $data = json_encode(array(
            'data' => array(
                'name' => 'Example Collection'
            )
        ));

        $client = $this->getDataAPIClientMock('getDataverseCollectionData', self::SUCCESS, $data);
        $service = new DataAPIService($client);

        $this->assertEquals(
            'Example Collection',
            $service->getDataverseCollectionName()
        );
    }

    public function testTrownExceptionWhenAPIRequestFail(): void
    {
        $this->expectExceptionCode(self::FAIL);
        $this->expectExceptionMessage('Error Processing Request');

        $client = $this->getDataAPIClientMock('getDataverseServerData', self::FAIL);
        $service = new DataAPIService($client);
        $service->getDataverseServerName();
    }
}
