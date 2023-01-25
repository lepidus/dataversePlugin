<?php

import('plugins.generic.dataverse.classes.factories.dataset.SubmissionDatasetFactory');
import('plugins.generic.dataverse.classes.creators.DataversePackageCreator');
import('plugins.generic.dataverse.classes.adapters.SubmissionAdapter');
import('plugins.generic.dataverse.classes.adapters.AuthorAdapter');
import('plugins.generic.dataverse.classes.file.DraftDatasetFile');
import('plugins.generic.dataverse.classes.entities.Dataset');
import('plugins.generic.dataverse.classes.entities.DatasetAuthor');
import('plugins.generic.dataverse.classes.entities.DatasetContact');
import('lib.pkp.tests.PKPTestCase');

define('ATOM_ENTRY_XML_NAMESPACE', 'http://www.w3.org/2005/Atom');
define('ATOM_ENTRY_XML_DCTERMS', 'http://purl.org/dc/terms/');

class DataversePackageCreatorTest extends PKPTestCase
{
    private $id = 9090;
    private $title = 'The Rise of The Machine Empire';
    private $description = 'An example abstract';
    private $subject = 'N/A';
    private $keywords = array('Modern History');
    private $citation = 'test citation';
    private $author;
    private $contact;
    private $authors = array();
    private $files = array();

    public function setUp(): void
    {
        parent::setUp();
        $this->createTestAuthor();
        $this->createTestContact();
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

    private function createTestAuthor(): void
    {
        $this->author = new DatasetAuthor('Castanheiras, Íris', 'Dataverse');
    }

    private function createTestContact(): void
    {
        $this->contact = new DatasetContact('Castanheiras, Íris', 'iriscastaneiras@testemail.com');
    }

    private function createDefaultTestAtomEntry(): void
    {
        $dataset = new Dataset();
        $dataset->setTitle($this->title);
        $dataset->setDescription($this->description);
        $dataset->setSubject($this->subject);
        $dataset->setKeywords($this->keywords);
        $dataset->setAuthors(array($this->author));
        $dataset->setContact($this->contact);
        $dataset->setCitation($this->citation);

        $this->packageCreator->loadMetadata($dataset);
        $this->packageCreator->createAtomEntry();
    }

    private function createDefaultTestAtomEntryFromSubmission(): void
    {
        $author = new AuthorAdapter(
            "Íris",
            "Castanheiras",
            $this->author->getAffiliation(),
            $this->contact->getEmail()
        );
        $file = new DraftDatasetFile();
        $file->setData('sponsor', 'CAPES');
        array_push($this->authors, $author);
        array_push($this->files, $file);

        $submission = new SubmissionAdapter();
        $submission->setRequiredData(
            $this->id,
            $this->title,
            $this->description,
            $this->subject,
            $this->keywords,
            $this->citation,
            $this->contact,
            $this->authors,
            $this->files
        );

        $factory = new SubmissionDatasetFactory($submission);
        $dataset = $factory->getDataset();
        $this->packageCreator->loadMetadata($dataset);
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

        $this->assertXmlFileEqualsXmlFile(dirname(__FILE__, 2) . '/assets/atomEntryExampleForTesting.xml', $this->packageCreator->getAtomEntryPath());
    }

    public function testValidateAtomEntryFromSubmissionXmlFileStructure(): void
    {
        $this->createDefaultTestAtomEntryFromSubmission();

        $this->assertXmlFileEqualsXmlFile(dirname(__FILE__, 2) . '/assets/atomEntryExampleForTesting.xml', $this->packageCreator->getAtomEntryPath());
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
            'atomEntryCreator' => $atom->getElementsByTagName('creator')->item(0)->nodeValue
        );
        $expectedMetadata = array(
            'atomEntryTitle' => $this->title,
            'atomEntryDescription' => $this->description,
            'atomEntrySubject' => $this->keywords[0],
            'atomEntryCreator' => $this->author->getName()
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
            'atomEntryCreator' => $atom->getElementsByTagName('creator')->item(0)->nodeValue
        );
        $expectedMetadata = array(
            'atomEntryTitle' => $this->title,
            'atomEntryDescription' => $this->description,
            'atomEntrySubject' => $this->keywords[0],
            'atomEntryCreator' => $this->author->getName()
        );

        $this->assertEquals($expectedMetadata, $atomEntryMetadata);
    }

    public function testCreatePackageWithSampleFile(): void
    {
        $this->createDefaultTestAtomEntry();

        $this->packageCreator->addFileToPackage(dirname(__FILE__, 2) . '/assets/testSample.csv', "sampleFileForTests.csv");
        $this->packageCreator->createPackage();

        $this->assertTrue(file_exists($this->packageCreator->getPackageFilePath()));
    }

    public function testCreatePackageFromSubmissionWithSampleFile(): void
    {
        $this->createDefaultTestAtomEntryFromSubmission();

        $this->packageCreator->addFileToPackage(dirname(__FILE__, 2) . '/assets/testSample.csv', "sampleFileForTests.csv");
        $this->packageCreator->createPackage();

        $this->assertTrue(file_exists($this->packageCreator->getPackageFilePath()));
    }
}
