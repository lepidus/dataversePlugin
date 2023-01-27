<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.dataverseAPI.DataverseAPIService');
import('plugins.generic.dataverse.classes.entities.DataverseResponse');

class DataverseAPIServiceTest extends PKPTestCase
{
    private const SUCCESS = 200;

    private const FAIL = 400;

    private $dataset;

    private $author;

    private $contact;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestDataset();
    }

    private function createTestDataset(): void
    {
        $contact = new DatasetContact('User, Test', 'testuser@example.com', 'Dataverse');
        $author = new DatasetAuthor('User, Test', 'Dataverse', '0000-0000-0000-0000');

        $dataset = new Dataset();
        $dataset->setTitle('Test Dataset');
        $dataset->setDescription('<p>Test description</p>');
        $dataset->setAuthors(array($author));
        $dataset->setSubject('Other');
        $dataset->setKeywords(array('test'));
        $dataset->setContact($contact);
        $dataset->setPubCitation('User, T. (2023). <em>Test Dataset</em>. Open Preprint Systems');
        $dataset->setCitation(
            'Test, User, 2023, "Test Dataset", <a href="https://doi.org/10.12345/ABC/DEFGHI">https://doi.org/10.12345/ABC/DEFGHI</a>, Demo Dataverse, V1'
        );
        $dataset->setData('depositor', 'User, Test (via Open Preprint Systems)');

        $this->dataset = $dataset;
        $this->author = $author;
        $this->contact = $contact;
    }

    private function getDataClientMock(int $responseState): IDataAPIClient
    {
        $response = $this->getClientResponse($responseState);

        $clientMock = $this->getMockBuilder(IDataAPIClient::class)
            ->setMethods(array( 'getDatasetData', 'getDatasetFactory'))
            ->getMock();

        $clientMock->expects($this->any())
            ->method('getDatasetData')
            ->will($this->returnValue($response));

        $clientMock->expects($this->any())
            ->method('getDatasetFactory')
            ->will($this->returnValue(new NativeAPIDatasetFactory($response)));

        return $clientMock;
    }

    private function getClientResponse(int $responseState): DataverseResponse
    {
        $statusCode = $responseState;

        if ($responseState == self::SUCCESS) {
            $message = 'OK';
            $data = file_get_contents(__DIR__ . '/../assets/nativeAPIDatasetResponseExample.json');
        } else {
            $message = 'Error Processing Request';
            $data = null;
        }

        return new DataverseResponse($statusCode, $message, $data);
    }

    public function testServiceSuccessfullyReturnsDatasetData(): void
    {
        $persistentId = 'doi:10.1234/AB5/CD6EF7';

        $client = $this->getDataClientMock(self::SUCCESS);

        $service = new DataverseAPIService();

        $dataset = $service->getDataset($persistentId, $client);

        $this->assertEquals($this->dataset, $dataset);
    }

    public function testServiceThrownExceptionWhenRequestFail(): void
    {
        $this->expectExceptionCode(self::FAIL);
        $this->expectExceptionMessage('Error Processing Request');

        $persistentId = 'doi:10.1234/AB5/CD6EF7';

        $client = $this->getDataClientMock(self::FAIL);

        $service = new DataverseAPIService();

        $dataset = $service->getDataset($persistentId, $client);
    }
}
