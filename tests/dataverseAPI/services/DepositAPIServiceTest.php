<?php

import('lib.pkp.tests.DatabaseTestCase');
import('plugins.generic.dataverse.classes.dataverseAPI.services.DepositAPIService');
import('plugins.generic.dataverse.classes.entities.DataverseResponse');

class DepositAPIServiceTest extends DatabaseTestCase
{
    private const SUCCESS = 200;

    private const FAIL = 400;

    private $dataset;

    private $author;

    private $contact;

    private $submission;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestDataset();
        $dataverseStudyDAO = new DataverseStudyDAO();
        DAORegistry::registerDAO('DataverseStudyDAO', $dataverseStudyDAO);
    }

    protected function getAffectedTables(): array
    {
        return array('dataverse_studies');
    }

    private function createTestDataset(): void
    {
        $contact = new DatasetContact('User, Test', 'testuser@example.com', 'Dataverse');
        $author = new DatasetAuthor('User, Test', 'Dataverse', '0000-0000-0000-0000');

        $file = new DatasetFile();
        $file->setOriginalFileName('sample.csv');
        $file->setPath(__DIR__ . '/../../assets/testSample.csv');

        $dataset = new Dataset();
        $dataset->setTitle('Test Dataset');
        $dataset->setDescription('<p>Test description</p>');
        $dataset->setAuthors([$author]);
        $dataset->setSubject('Other');
        $dataset->setKeywords(['test']);
        $dataset->setContact($contact);
        $dataset->setPubCitation('User, T. (2023). <em>Test Dataset</em>. Open Preprint Systems');
        $dataset->setDepositor('User, Test (via Open Preprint Systems)');
        $dataset->setFiles([$file]);

        $this->dataset = $dataset;
        $this->author = $author;
        $this->contact = $contact;
    }

    private function getDepositClientMock(int $responseState): IDepositAPIClient
    {
        $response = $this->getDepositClientResponse($responseState);

        $clientMock = $this->getMockBuilder(IDepositAPIClient::class)
            ->setMethods([
                'depositDataset',
                'depositDatasetFiles',
                'getDatasetPackager'
            ])
            ->getMock();

        $clientMock->expects($this->any())
            ->method('depositDataset')
            ->will($this->returnValue($response));

        $clientMock->expects($this->any())
            ->method('depositDatasetFiles')
            ->will($this->returnValue($response));

        $clientMock->expects($this->any())
            ->method('getDatasetPackager')
            ->will($this->returnValue(new SWORDAPIDatasetPackager($this->dataset)));

        return $clientMock;
    }

    private function getDepositClientResponse(int $responseState): DataverseResponse
    {
        $statusCode = $responseState;

        if ($responseState == self::SUCCESS) {
            $message = 'OK';
            $data = json_encode(
                [
                    'editUri' => 'https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit/study/doi:10.1234/AB5/CD6EF7',
                    'editUri' => 'https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit/study/doi:10.1234/AB5/CD6EF7',
                    'editMediaUri' => 'https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit-media/study/doi:10.1234/AB5/CD6EF7',
                    'statementUri' => 'https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/statement/study/doi:10.1234/AB5/CD6EF7',
                    'persistentUri' => 'https://doi.org/10.1234/AB5/CD6EF7',
                    'persistentId' => 'doi:10.1234/AB5/CD6EF7'
                ]
            );
        } else {
            $message = 'Error Processing Request';
            $data = null;
        }

        return new DataverseResponse($statusCode, $message, $data);
    }

    public function testDatasetIsNotDepositedWithoutFiles(): void
    {
        $this->dataset->setFiles(null);
        $client = $this->getDepositClientMock(self::SUCCESS);

        $service = new DepositAPIService($client);

        $result = $service->depositDataset($this->dataset);

        $this->assertNull($result);
    }

    public function testServiceReturnsStudyWhenDepositIsSuccessful(): void
    {
        $client = $this->getDepositClientMock(self::SUCCESS);

        $expectedDatasetData = [
            'editUri' => 'https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit/study/doi:10.1234/AB5/CD6EF7',
            'editUri' => 'https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit/study/doi:10.1234/AB5/CD6EF7',
            'editMediaUri' => 'https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit-media/study/doi:10.1234/AB5/CD6EF7',
            'statementUri' => 'https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/statement/study/doi:10.1234/AB5/CD6EF7',
            'persistentUri' => 'https://doi.org/10.1234/AB5/CD6EF7',
            'persistentId' => 'doi:10.1234/AB5/CD6EF7'
        ];

        $service = new DepositAPIService($client);
        $datasetData = $service->depositDataset($this->dataset);

        $this->assertEquals($expectedDatasetData, $datasetData);
    }
}
