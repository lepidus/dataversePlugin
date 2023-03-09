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
            ->setMethods(array($method, 'getDatasetFactory', 'retrieveDatasetFiles'))
            ->getMock();

        $clientMock->expects($this->any())
            ->method($method)
            ->will($this->returnValue($response));

        $clientMock->expects($this->any())
            ->method('getDatasetFactory')
            ->will($this->returnValue(new NativeAPIDatasetFactory($response)));

        $firstDatasetFile = new DatasetFile();
        $firstDatasetFile->setId(2025434);
        $firstDatasetFile->setTitle('Sample.jpg');

        $secondDatasetFile = new DatasetFile();
        $secondDatasetFile->setId(2025433);
        $secondDatasetFile->setTitle('DataTable.tab');

        $clientMock->expects($this->any())
            ->method('retrieveDatasetFiles')
            ->will($this->returnValue([
                $firstDatasetFile,
                $secondDatasetFile
            ]));

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

    public function testServiceSuccessfullyReturnsDatasetData(): void
    {
        $persistentId = 'doi:10.1234/AB5/CD6EF7';
        $data = file_get_contents(__DIR__ . '/../../assets/nativeAPIDatasetResponseExample.json');

        $client = $this->getDataAPIClientMock('getDatasetData', self::SUCCESS, $data);

        $service = new DataAPIService($client);
        $dataset = $service->getDataset($persistentId);

        $contact = new DatasetContact('User, Test', 'testuser@example.com', 'Dataverse');
        $author = new DatasetAuthor('User, Test', 'Dataverse', '0000-0000-0000-0000');

        $expectedDataset = new Dataset();
        $expectedDataset->setTitle('Test Dataset');
        $expectedDataset->setDescription('<p>Test description</p>');
        $expectedDataset->setAuthors(array($author));
        $expectedDataset->setSubject('Other');
        $expectedDataset->setKeywords(array('test'));
        $expectedDataset->setContact($contact);
        $expectedDataset->setPubCitation('User, T. (2023). <em>Test Dataset</em>. Open Preprint Systems');
        $expectedDataset->setDepositor('User, Test (via Open Preprint Systems)');

        $this->assertEquals($expectedDataset, $dataset);
    }

    public function testTrownExceptionWhenAPIRequestFail(): void
    {
        $this->expectExceptionCode(self::FAIL);
        $this->expectExceptionMessage('Error Processing Request');

        $client = $this->getDataAPIClientMock('getDataverseServerData', self::FAIL);
        $service = new DataAPIService($client);
        $service->getDataverseServerName();
    }

    public function testServiceSuccessfullyReturnsDatasetFilesData(): void
    {
        $persistentId = 'doi:10.1234/AB5/CD6EF7';
        $data = file_get_contents(__DIR__ . '/../../assets/nativeAPIDatasetFilesResponseExample.json');
        $firstDatasetFile = new DatasetFile();
        $firstDatasetFile->setId(2025434);
        $firstDatasetFile->setTitle('Sample.jpg');

        $secondDatasetFile = new DatasetFile();
        $secondDatasetFile->setId(2025433);
        $secondDatasetFile->setTitle('DataTable.tab');

        $expectedDatasetFiles = array(
            $firstDatasetFile,
            $secondDatasetFile
        );

        $client = $this->getDataAPIClientMock('getDatasetFilesData', self::SUCCESS, $data);

        $service = new DataAPIService($client);
        $datasetFiles = $service->getDatasetFiles($persistentId);

        $this->assertEquals($expectedDatasetFiles, $datasetFiles);
    }
}
