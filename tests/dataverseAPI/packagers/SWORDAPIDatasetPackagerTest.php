<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.dataverseAPI.packagers.SWORDAPIDatasetPackager');

class SWORDAPIDatasetPackagerTest extends PKPTestCase
{
    private $dataset;

    private $packager;

    protected function setUp(): void
    {
        $this->createPackager();
    }

    public function tearDown(): void
    {
        $this->packager->clear();
        parent::tearDown();
    }

    public function createPackager(): void
    {
        $author = new DatasetAuthor('User, Test', 'Dataverse', '0000-0000-0000-0000');
        $contact = new DatasetContact('User, Test', 'testuser@example.com', 'Dataverse');

        $this->dataset = new Dataset();
        $this->dataset->setTitle('Test Dataset');
        $this->dataset->setDescription('<p>Test description</p>');
        $this->dataset->setAuthors(array($author));
        $this->dataset->setSubject('Other');
        $this->dataset->setKeywords(array('test'));
        $this->dataset->setContact($contact);
        $this->dataset->setPubCitation('User, T. (2023). <em>Test Dataset</em>. Open Preprint Systems');
        $this->dataset->setDepositor('User, Test (via Open Preprint Systems)');

        $this->packager = new SWORDAPIDatasetPackager($this->dataset);
    }

    public function testPackagerReturnsCorrectPackagePath(): void
    {
        $packagePath = $this->packager->getPackagePath();
        $this->assertMatchesRegularExpression('/\/tmp\/dataverse.+\/files\/atom/', $packagePath);
    }

    public function testPackagerLoadedMetadataFromDataset(): void
    {
        $this->packager->createDatasetPackage();

        $atom = new DOMDocument();
        $atom->load($this->packager->getPackagePath());

        $packageMetadata = array(
            'atomEntryTitle' => $atom->getElementsByTagName('title')->item(0)->nodeValue,
            'atomEntryDescription' => $atom->getElementsByTagName('description')->item(0)->nodeValue,
            'atomEntryIsReferencedBy' => $atom->getElementsByTagName('isReferencedBy')->item(0)->nodeValue,
            'atomEntryCreator' => $atom->getElementsByTagName('creator')->item(0)->nodeValue,
            'atomEntrySubject' => $atom->getElementsByTagName('subject')->item(0)->nodeValue
        );
        $expectedMetadata = array(
            'atomEntryTitle' => $this->dataset->getTitle(),
            'atomEntryDescription' => $this->dataset->getDescription(),
            'atomEntryIsReferencedBy' => $this->dataset->getPubCitation(),
            'atomEntryCreator' => $this->dataset->getAuthors()[0]->getName(),
            'atomEntrySubject' => $this->dataset->getKeywords()[0]
        );

        $this->assertEquals($expectedMetadata, $packageMetadata);
    }
}
