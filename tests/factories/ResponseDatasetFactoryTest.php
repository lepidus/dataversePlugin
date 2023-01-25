<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.factories.dataset.ResponseDatasetFactory');

class ResponseDatasetFactoryTest extends PKPTestCase
{
    private $dataset;

    private $response;

    protected function setUp(): void
    {
        $this->createTestDataset();
        $this->createTestResponse();
    }

    private function createTestDataset(): void
    {
        $contact = new DatasetContact('Castanheiras, Íris', 'iris@testmail.com', 'Dataverse');
        $author = new DatasetAuthor('Castanheiras, Íris', 'Dataverse', '0000-0000-0000-0000');

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
    }

    private function createTestResponse(): void
    {
        $contact = $this->dataset->getContact();
        $authors = $this->dataset->getAuthors();

        $statusCode = 200;
        $message = 'OK';
        $body = [
            'title' => $this->dataset->getTitle(),
            'description' => $this->dataset->getDescription(),
            'authors' => [
                [
                    'name' => $authors[0]->getName(),
                    'affiliation' => $authors[0]->getAffiliation(),
                    'identifier' => $authors[0]->getIdentifier(),
                ]
            ],
            'subject' => $this->dataset->getSubject(),
            'keywords' => $this->dataset->getKeywords(),
            'contact' => [
                'name' => $contact->getName(),
                'email' => $contact->getEmail(),
                'affiliation' => $contact->getAffiliation()
            ],
            'pubCitation' => $this->dataset->getPubCitation(),
            'citation' => $this->dataset->getCitation()
        ];

        $response = new DataverseResponse($statusCode, $message, $body);
        $this->response = $response;
    }

    public function testSuccessfullyCreatesDatasetWithResponseData(): void
    {
        $data = $this->response->getBody();
        $factory = new ResponseDatasetFactory($data);
        $dataset = $factory->getDataset();

        $this->assertEquals($this->dataset, $dataset);
    }
}
