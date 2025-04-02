<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.factories.JsonDatasetFactory');

class JsonDatasetFactoryTest extends PKPTestCase
{
    public function testCreateDataset()
    {
        $jsonContent = file_get_contents(__DIR__ . '/../fixtures/datasetVersionsResponseExample.json');
        $datasetFactory = new JsonDatasetFactory($jsonContent);
        $dataset = $datasetFactory->getDataset();

        $this->assertEquals('doi:10.12345/FK2/ABCDEFG', $dataset->getPersistentId());
        $this->assertEquals('Test title', $dataset->getTitle());
        $this->assertEquals('<p>An example description</p>', $dataset->getDescription());
        $this->assertEquals('CC BY 4.0', $dataset->getLicense());
        $this->assertEquals('Other', $dataset->getSubject());
        $this->assertEquals(['test'], $dataset->getKeywords());
        $this->assertEquals('Test, Author', $dataset->getAuthors()[0]->getName());
        $this->assertEquals('Dataverse', $dataset->getAuthors()[0]->getAffiliation());
        $this->assertEquals('0000-0000-0000-0000', $dataset->getAuthors()[0]->getIdentifier());
        $this->assertEquals('ORCID', $dataset->getAuthors()[0]->getIdentifierScheme());
        $this->assertEquals('Test, Contact', $dataset->getContact()->getName());
        $this->assertEquals('test@example.com', $dataset->getContact()->getEmail());
        $this->assertEquals('Dataverse', $dataset->getContact()->getAffiliation());
        $this->assertEquals('Related publication', $dataset->getPubCitation());
        $this->assertEquals('Test, Depositor', $dataset->getDepositor());
        $this->assertEquals('RELEASED', $dataset->getVersionState());

        $this->assertEquals(9876543, $dataset->getFiles()[0]->getId());
        $this->assertEquals('sample.pdf', $dataset->getFiles()[0]->getFileName());
        $this->assertEquals('sample.pdf', $dataset->getFiles()[0]->getOriginalFileName());
    }
}
