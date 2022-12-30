<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.creators.DataversePackageCreator');
import('plugins.generic.dataverse.classes.adapters.AuthorAdapter');
import('plugins.generic.dataverse.classes.adapters.SubmissionAdapter');
import('plugins.generic.dataverse.classes.DatasetModel');
import('plugins.generic.dataverse.classes.creators.DatasetFactory');

define('ATOM_ENTRY_XML_NAMESPACE', 'http://www.w3.org/2005/Atom');
define('ATOM_ENTRY_XML_DCTERMS', 'http://purl.org/dc/terms/');

class DataversePackageCreatorTest extends PKPTestCase
{
    private $packageCreator;
    private $submissionAdapter;

    private $contextId = 1;
    private $submissionId;
    private $publicationId;

    private $keywords = ["en_US" => array("computer science")];
    private $authors = array();
    private $files = array();
    private $locale = 'en_US';
    private $dateSubmitted = '2021-05-31 15:38:24';
    private $dateLastActivity = '2021-06-03 16:00:00';
    private $statusCode = "STATUS_PUBLISHED";

    private $id          = 1;
    private $title       = 'The Rise of The Machine Empire';
    private $description = 'An example abstract';
    private $creator     = array("Irís Castanheiras");
    private $subject     = array();
    private $contributor = array(array('Funder' => 'CAPES'));

    public function setUp(): void
    {
        parent::setUp();
        $this->packageCreator = new DataversePackageCreator();
    }

    public function tearDown(): void
    {
        if (file_exists($this->packageCreator->getAtomEntryPath())) {
            unlink($this->packageCreator->getAtomEntryPath());
        }
        if (file_exists($this->packageCreator->getPackageFilePath())) {
            unlink($this->packageCreator->getPackageFilePath());
        }
        rmdir($this->packageCreator->getOutPath() . '/files');
        rmdir($this->packageCreator->getOutPath());
        parent::tearDown();
    }

    private function createDefaultTestAtomEntry(): void
    {
        $datasetModel = new DatasetModel($this->title, $this->creator, $this->subject, $this->description, $this->contributor);
        $this->packageCreator->loadMetadata($datasetModel);
        $this->packageCreator->createAtomEntry();
    }

    private function createDefaultTestAtomEntryFromSubmission(): void
    {
        $this->authors[] = new AuthorAdapter("Irís", "Castanheiras", 'Universidade de São Paulo', 'iris@lepidus.com.br');
        $draftDatasetFile = new DraftDatasetFile();
        $draftDatasetFile->setData('sponsor', 'CAPES');
        array_push($this->files, $draftDatasetFile);
        $this->submissionAdapter = new SubmissionAdapter($this->id, $this->title, $this->authors, $this->files, $this->description, $this->subject);

        $datasetFactory = new DatasetFactory();
        $this->datasetModel = $datasetFactory->build($this->submissionAdapter);

        $this->packageCreator->loadMetadata($this->datasetModel);
        $this->packageCreator->createAtomEntry();
    }

    public function testCreateAtomEntryInLocalTempFiles(): void
    {
        $this->createDefaultTestAtomEntry();

        $this->assertEquals($this->packageCreator->getOutPath() . '/files/atom', $this->packageCreator->getAtomEntryPath());
        $this->assertTrue(file_exists($this->packageCreator->getAtomEntryPath()));
    }

    public function testValidateAtomEntryXmlFileStructure(): void
    {
        $this->createDefaultTestAtomEntry();

        $this->assertXmlFileEqualsXmlFile(dirname(__FILE__) . '/assets/atomEntryExampleForTesting.xml', $this->packageCreator->getAtomEntryPath());
    }

    public function testValidateAtomEntryFromSubmissionXmlFileStructure(): void
    {
        $this->createDefaultTestAtomEntryFromSubmission();

        $this->assertXmlFileEqualsXmlFile(dirname(__FILE__) . '/assets/atomEntryExampleForTesting.xml', $this->packageCreator->getAtomEntryPath());
    }

    public function testValidateAtomEntryNamespaceAttributes(): void
    {
        $this->createDefaultTestAtomEntry();

        $atom = new DOMDocument();
        $atom->load($this->packageCreator->getAtomEntryPath());
        $entry = $atom->getElementsByTagName('entry')->item(0);

        $xmlns = $entry->getAttribute('xmlns');
        $xmlnsDcTerms = $entry->getAttribute('xmlns:dcterms');

        $this->assertEquals($xmlns, ATOM_ENTRY_XML_NAMESPACE);
        $this->assertEquals($xmlnsDcTerms, ATOM_ENTRY_XML_DCTERMS);
    }

    public function testValidateAtomEntryRequiredMetadata(): void
    {
        $this->createDefaultTestAtomEntry();

        $atom = new DOMDocument();
        $atom->load($this->packageCreator->getAtomEntryPath());
        $atomEntryMetadata = array(
            'atomEntryTitle' => $atom->getElementsByTagName('title')->item(0)->nodeValue,
            'atomEntryDescription' => $atom->getElementsByTagName('description')->item(0)->nodeValue,
            'atomEntrySubject' => $atom->getElementsByTagName('subject')->item(0)->nodeValue,
            'atomEntryCreator' => $atom->getElementsByTagName('creator')->item(0)->nodeValue,
            'atomEntryContributor' => $atom->getElementsByTagName('contributor')->item(0)->nodeValue
        );
        $expectedMetadata = array(
            'atomEntryTitle' => $this->title,
            'atomEntryDescription' => $this->description,
            'atomEntrySubject' => 'N/A',
            'atomEntryCreator' => $this->creator[0],
            'atomEntryContributor' => $this->contributor[0]['Funder']
        );

        $this->assertEquals($expectedMetadata, $atomEntryMetadata);
    }

    public function testValidateAtomEntryFromSubmissionRequiredMetadata(): void
    {
        $this->createDefaultTestAtomEntryFromSubmission();

        $atom = new DOMDocument();
        $atom->load($this->packageCreator->getAtomEntryPath());

        $atomEntryMetadata = array(
            'atomEntryTitle' => $atom->getElementsByTagName('title')->item(0)->nodeValue,
            'atomEntryDescription' => $atom->getElementsByTagName('description')->item(0)->nodeValue,
            'atomEntrySubject' => $atom->getElementsByTagName('subject')->item(0)->nodeValue,
            'atomEntryCreator' => $atom->getElementsByTagName('creator')->item(0)->nodeValue,
            'atomEntryContributor' => $atom->getElementsByTagName('contributor')->item(0)->nodeValue
        );
        $expectedMetadata = array(
            'atomEntryTitle' => $this->title,
            'atomEntryDescription' => $this->description,
            'atomEntrySubject' => 'N/A',
            'atomEntryCreator' => $this->creator[0],
            'atomEntryContributor' => $this->contributor[0]['Funder']
        );

        $this->assertEquals($expectedMetadata, $atomEntryMetadata);
    }

    public function testCreatePackageWithSampleFile(): void
    {
        $this->createDefaultTestAtomEntry();

        $this->packageCreator->addFileToPackage(dirname(__FILE__) . '/assets/testSample.csv', "sampleFileForTests.csv");
        $this->packageCreator->createPackage();

        $this->assertTrue(file_exists($this->packageCreator->getPackageFilePath()));
    }

    public function testCreatePackageFromSubmissionWithSampleFile(): void
    {
        $this->createDefaultTestAtomEntryFromSubmission();

        $this->packageCreator->addFileToPackage(dirname(__FILE__) . '/assets/testSample.csv', "sampleFileForTests.csv");
        $this->packageCreator->createPackage();

        $this->assertTrue(file_exists($this->packageCreator->getPackageFilePath()));
    }
}
