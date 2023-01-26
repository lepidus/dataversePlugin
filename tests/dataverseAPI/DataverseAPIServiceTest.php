<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.dataverseAPI.DataverseAPIService');
import('plugins.generic.dataverse.classes.entities.DataverseResponse');

class DataverseAPIServiceTest extends PKPTestCase
{
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
        $contact = new DatasetContact('Castanheiras, Íris', 'iris@testmail.com', 'Dataverse');
        $author = new DatasetAuthor('Castanheiras, Íris', 'Dataverse');

        $dataset = new Dataset();
        $dataset->setTitle('test title');
        $dataset->setDescription('test description');
        $dataset->setAuthors(array($author));
        $dataset->setSubject('Other');
        $dataset->setKeywords(array());
        $dataset->setContact($contact);
        $dataset->setPubCitation('test related publication citation');
        $dataset->setCitation('test dataset citation');

        $this->dataset = $dataset;
        $this->author = $author;
        $this->contact = $contact;
    }

    private function getDataClientMock(): IDataAPIClient
    {
        $statusCode = 200;
        $message = 'OK';
        $body = [
            'title' => $this->dataset->getTitle(),
            'description' => $this->dataset->getDescription(),
            'authors' => [
                [
                    'name' => $this->author->getName(),
                    'affiliation' => $this->author->getAffiliation(),
                    'identifier' => $this->author->getIdentifier(),
                ]
            ],
            'subject' => $this->dataset->getSubject(),
            'keywords' => $this->dataset->getKeywords(),
            'contact' => [
                'name' => $this->contact->getName(),
                'email' => $this->contact->getEmail(),
                'affiliation' => $this->contact->getAffiliation()
            ],
            'pubCitation' => $this->dataset->getPubCitation(),
            'citation' => $this->dataset->getCitation()
        ];

        $response = new DataverseResponse($statusCode, $message, $body);

        $clientMock = $this->getMockBuilder(IDataAPIClient::class)
            ->setMethods(array('getDatasetData'))
            ->getMock();

        $clientMock->expects($this->any())
            ->method('getDatasetData')
            ->will($this->returnValue($response));

        return $clientMock;
    }

    public function testServiceSuccessfullyReturnsDatasetData(): void
    {
        $persistentId = 'doi:10.1234/AB5/CD6EF7';

        $client = $this->getDataClientMock();

        $service = new DataverseAPIService();

        $dataset = $service->getDataset($persistentId, $client);

        $this->assertEquals($this->dataset, $dataset);
    }
}
