<?php

import('plugins.generic.dataverse.classes.factories.dataset.NativeAPIDatasetFactory');
import('plugins.generic.dataverse.classes.entities.DataverseResponse');
import('lib.pkp.tests.PKPTestCase');

class NativeAPIDatasetFactoryTest extends PKPTestCase
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
            'Test, User, 2023, "Test Dataset", https://doi.org/10.12345/ABC/DEFGHI, Demo Dataverse, V1, UNF:6:dEgtc5Z1MSF3u7c+kF4kXg== [fileUNF]'
        );
        $dataset->setData('depositor', 'User, Test (via Open Preprint Systems)');

        $this->dataset = $dataset;
    }

    private function createTestResponse(): void
    {
        $contact = $this->dataset->getContact();
        $authors = $this->dataset->getAuthors();

        $statusCode = 200;
        $message = 'OK';
        $data = file_get_contents(__DIR__ . '/../assets/nativeAPIDatasetResponseExample.json');

        $this->response = new DataverseResponse($statusCode, $message, $data);
    }

    public function testSuccessfullyCreatesDatasetWithResponseData(): void
    {
        $factory = new NativeAPIDatasetFactory($this->response);
        $dataset = $factory->getDataset();

        $this->assertEquals($this->dataset, $dataset);
    }
}
