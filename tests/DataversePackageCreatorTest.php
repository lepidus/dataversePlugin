<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.DataversePackageCreator');
import('plugins.generic.dataverse.classes.DatasetModel');

class DataversePackageCreatorTest extends PKPTestCase
{
    private $packageCreator;

    const ATOM_ENTRY_XML_NAMESPACE = "http://www.w3.org/2005/Atom";
    const ATOM_ENTRY_XML_DCTERMS = "http://purl.org/dc/terms/";

    private $title       = 'The Rise of The Machine Empire';
    private $description = 'An example abstract';
    private $creator     = 'Irís Castanheiras';
    private $subject     = 'Computer and Information Science';
    private $contributor = 'iris@lepidus.com.br';

    public function setUp(): void
    {
        $this->packageCreator = new DataversePackageCreator();
        parent::setUp();
    }

    public function tearDown(): void
    {
        if (file_exists($this->packageCreator->getAtomEntryPath())) {
            unlink($this->packageCreator->getAtomEntryPath());
        }
        rmdir($this->packageCreator->getOutPath() . '/files');
        rmdir($this->packageCreator->getOutPath());
        parent::tearDown();
    }

    private function createDefaultTestAtomEntry(): void
    {
        $datasetModel = new DatasetModel($this->title, $this->creator, $this->subject, $this->description, $this->contributor);
        $this->packageCreator->loadMetadataFromDatasetModel($datasetModel);
        $this->packageCreator->createAtomEntry();
    }

    public function testCreateAtomEntryInLocalTempFiles(): void
    {
        $this->createDefaultTestAtomEntry();

        $this->assertEquals($this->packageCreator->getOutPath() . '/files/atom', $this->packageCreator->getAtomEntryPath());
        $this->assertTrue(file_exists($this->packageCreator->getAtomEntryPath()));
    }

    public function testValidateAtomEntryNamespaceAttributes(): void
    {
        $this->createDefaultTestAtomEntry();

        $atom = DOMDocument::load($this->packageCreator->getAtomEntryPath());
        $entry = $atom->getElementsByTagName('entry')->item(0);

        $xmlns = $entry->getAttribute('xmlns');
        $xmlnsDcTerms = $entry->getAttribute('xmlns:dcterms');
        
        $this->assertEquals($xmlns, self::ATOM_ENTRY_XML_NAMESPACE);
        $this->assertEquals($xmlnsDcTerms, self::ATOM_ENTRY_XML_DCTERMS);
    }

    public function testValidateAtomEntryRequiredMetadata(): void
    {
        $this->createDefaultTestAtomEntry();

        $atom = DOMDocument::load($this->packageCreator->getAtomEntryPath());
        
        $atomEntryMetadata = array(
            'atomEntryTitle' => $atom->getElementsByTagName('title')->item(0)->nodeValue,
            'atomEntryDescription' => $atom->getElementsByTagName('description')->item(0)->nodeValue,
            'atomEntryCreator' => $atom->getElementsByTagName('creator')->item(0)->nodeValue,
            'atomEntrySubject' => $atom->getElementsByTagName('subject')->item(0)->nodeValue,
            'atomEntryContributor' => $atom->getElementsByTagName('contributor')->item(0)->nodeValue
        );
        $expectedMetadata = array(
            'atomEntryTitle' => $this->title,
            'atomEntryDescription' => $this->description,
            'atomEntryCreator' => $this->creator,
            'atomEntrySubject' => $this->subject,
            'atomEntryContributor' => $this->contributor
        );

        $this->assertEquals($atomEntryMetadata, $expectedMetadata);
    }

    public function testValidateAtomEntryXmlFileStructure(): void
    {
        $this->createDefaultTestAtomEntry();

        $this->assertXmlFileEqualsXmlFile($this->packageCreator->getAtomEntryPath(), dirname(__FILE__) . '/atomEntryExampleForTesting.xml');
    }
}
