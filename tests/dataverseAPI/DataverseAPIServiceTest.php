<?php

import('lib.pkp.tests.DatabaseTestCase');
import('plugins.generic.dataverse.classes.dataverseAPI.DataverseAPIService');
import('plugins.generic.dataverse.classes.entities.DataverseResponse');

class DataverseAPIServiceTest extends DatabaseTestCase
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
        $dataset->setDepositor('User, Test (via Open Preprint Systems)');

        $this->dataset = $dataset;
        $this->author = $author;
        $this->contact = $contact;
    }

    private function createSubmission(): SubmissionAdapter
    {
        $author = new AuthorAdapter('test', 'user', 'Dataverse', 'user@test.com');

        $datasetContact = new DatasetContact(
            $author->getFullName(),
            $author->getEmail(),
            'Dataverse'
        );

        $file = new TemporaryFile();
        $file->setServerFileName('sample.pdf');
        $file->setOriginalFileName('sample.pdf');

        $submission = new SubmissionAdapter();
        $submission->setRequiredData(
            909,
            'Example title',
            'Example abstract',
            'Other',
            array('test'),
            'test citation',
            $datasetContact,
            'user, test (via Dataverse)',
            array($author),
            array($file)
        );

        return $submission;
    }

    private function getDataClientMock(int $responseState): IDataAPIClient
    {
        $response = $this->getDataClientResponse($responseState);

        $clientMock = $this->getMockBuilder(IDataAPIClient::class)
            ->setMethods(array('getDatasetData', 'getDatasetFactory'))
            ->getMock();

        $clientMock->expects($this->any())
            ->method('getDatasetData')
            ->will($this->returnValue($response));

        $clientMock->expects($this->any())
            ->method('getDatasetFactory')
            ->will($this->returnValue(new NativeAPIDatasetFactory($response)));

        return $clientMock;
    }

    private function getDepositClientMock(int $responseState): IDepositAPIClient
    {
        $response = $this->getDepositClientResponse($responseState);

        $clientMock = $this->getMockBuilder(IDepositAPIClient::class)
            ->setMethods(array('depositDataset', 'getDatasetPackager'))
            ->getMock();

        $clientMock->expects($this->any())
            ->method('depositDataset')
            ->will($this->returnValue($response));

        $clientMock->expects($this->any())
            ->method('getDatasetPackager')
            ->will($this->returnValue(new SWORDAPIDatasetPackager($this->dataset)));

        return $clientMock;
    }

    private function getDataClientResponse(int $responseState): DataverseResponse
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

    private function getDepositClientResponse(int $responseState): DataverseResponse
    {
        $statusCode = $responseState;

        if ($responseState == self::SUCCESS) {
            $message = 'OK';
            $data = json_encode(
                array(
                    'editUri' => 'https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit/study/doi:10.1234/AB5/CD6EF7',
                    'editUri' => 'https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit/study/doi:10.1234/AB5/CD6EF7',
                    'editMediaUri' => 'https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit-media/study/doi:10.1234/AB5/CD6EF7',
                    'statementUri' => 'https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/statement/study/doi:10.1234/AB5/CD6EF7',
                    'persistentUri' => 'https://doi.org/10.1234/AB5/CD6EF7',
                    'persistentId' => 'doi:10.1234/AB5/CD6EF7'
                )
            );
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

    public function testDatasetIsNotDepositedWithoutFiles(): void
    {
        $client = $this->getDepositClientMock(self::SUCCESS);

        $service = new DataverseAPIService();

        $submission = $this->createSubmission();
        $submission->setFiles(null);

        $result = $service->depositDataset($submission, $client);

        $this->assertNull($result);
    }

    public function testServiceReturnsStudyWhenDepositIsSuccessful(): void
    {
        $client = $this->getDepositClientMock(self::SUCCESS);

        $service = new DataverseAPIService();

        $submission = $this->createSubmission();

        $study = $service->depositDataset($submission, $client);

        $expectedStudy = new DataverseStudy();
        $expectedStudy->setId($study->getId());
        $expectedStudy->setSubmissionId($submission->getId());
        $expectedStudy->setEditUri('https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit/study/doi:10.1234/AB5/CD6EF7');
        $expectedStudy->setEditMediaUri('https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit-media/study/doi:10.1234/AB5/CD6EF7');
        $expectedStudy->setStatementUri('https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/statement/study/doi:10.1234/AB5/CD6EF7');
        $expectedStudy->setPersistentUri('https://doi.org/10.1234/AB5/CD6EF7');
        $expectedStudy->setPersistentId('doi:10.1234/AB5/CD6EF7');

        $this->assertEquals($expectedStudy, $study);
    }
}
