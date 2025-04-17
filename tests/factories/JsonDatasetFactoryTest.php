<?php

use PKP\tests\PKPTestCase;
use APP\plugins\generic\dataverse\classes\entities\DatasetAuthor;
use APP\plugins\generic\dataverse\classes\entities\DatasetContact;
use APP\plugins\generic\dataverse\classes\entities\DatasetRelatedPublication;
use APP\plugins\generic\dataverse\classes\factories\JsonDatasetFactory;

class JsonDatasetFactoryTest extends PKPTestCase
{
    public function testCreatesDatasetFromJsonResponse()
    {
        $jsonContent = file_get_contents(__DIR__ . '/../fixtures/datasetVersionsResponseExample.json');
        $datasetFactory = new JsonDatasetFactory($jsonContent);
        $dataset = $datasetFactory->getDataset();

        $expectedDatasetAuthor = new DatasetAuthor('Test, Author', 'Dataverse', 'ORCID', '0000-0000-0000-0000');
        $expectedDatasetContact = new DatasetContact('Test, Contact', 'test@example.com', 'Dataverse');
        $expectedDatasetRelatedPublication = new DatasetRelatedPublication(
            'User, T. (2023). <em>Test Dataset</em>. Open Preprint Systems',
            'doi',
            '10.1234/LepidusPreprints.1245',
            'https://doi.org/10.1234/LepidusPreprints.1245'
        );

        $this->assertEquals('doi:10.12345/FK2/ABCDEFG', $dataset->getPersistentId());
        $this->assertEquals('Test title', $dataset->getTitle());
        $this->assertEquals('<p>An example description</p>', $dataset->getDescription());
        $this->assertEquals('CC BY 4.0', $dataset->getLicense());
        $this->assertEquals('Other', $dataset->getSubject());
        $this->assertEquals(['test'], $dataset->getKeywords());
        $this->assertEquals($expectedDatasetAuthor, $dataset->getAuthors()[0]);
        $this->assertEquals($expectedDatasetContact, $dataset->getContact());
        $this->assertEquals($expectedDatasetRelatedPublication, $dataset->getRelatedPublication());
        $this->assertEquals('Test, Depositor', $dataset->getDepositor());
        $this->assertEquals('RELEASED', $dataset->getVersionState());

        $this->assertEquals(9876543, $dataset->getFiles()[0]->getId());
        $this->assertEquals('sample.pdf', $dataset->getFiles()[0]->getFileName());
        $this->assertEquals('sample.pdf', $dataset->getFiles()[0]->getOriginalFileName());
    }
}
