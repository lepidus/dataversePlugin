<?php

import('lib.pkp.tests.DatabaseTestCase');
import('plugins.generic.dataverse.classes.creators.DataversePackageCreator');
import('plugins.generic.dataverse.classes.DatasetModel');
import('plugins.generic.dataverse.classes.creators.SubmissionAdapterCreator');


class DataversePackageCreatorTest extends DatabaseTestCase
{
    public const ATOM_ENTRY_XML_NAMESPACE = "http://www.w3.org/2005/Atom";
    public const ATOM_ENTRY_XML_DCTERMS = "http://purl.org/dc/terms/";

    private $packageCreator;
    private $submissionAdapter;
    private $submissionAdapterCreator;

    private $contextId = 1;
    private $submissionId;
    private $publicationId;

    private $keywords = ["en_US" => array("computer science")];
    private $locale = 'en_US';
    private $dateSubmitted = '2021-05-31 15:38:24';
    private $dateLastActivity = '2021-06-03 16:00:00';
    private $statusCode = "STATUS_PUBLISHED";

    private $title       = 'The Rise of The Machine Empire';
    private $description = 'An example abstract';
    private $creator     = array("Irís Castanheiras");
    private $subject     = array("computer science");
    private $contributor = array("contact" => "iris@lepidus.com.br");

    public function setUp(): void
    {
        parent::setUp();
        
        $this->submissionAdapterCreator = new SubmissionAdapterCreator();
        $this->submissionId = $this->createTestSubmission();
        $this->publicationId = $this->createTestPublication();
        $authors = $this->createAuthors();
        $this->addCurrentPublicationToSubmission();

        $this->packageCreator = new DataversePackageCreator();

        $this->submissionAdapter = $this->submissionAdapterCreator->createSubmissionAdapter($this->submissionId);
    }

    protected function getAffectedTables()
    {
        return ['authors', 'submissions', 'publications', 'publication_settings', 'author_settings'];
    }

    private function createAuthors(): array
    {
        $creatorFullname = $this->creator[0];
        $creatorGivenName = explode(" ", $creatorFullname)[0];
        $creatorFamilyName = explode(" ", $creatorFullname)[1];

        $authorDao = DAORegistry::getDAO('AuthorDAO');
        $author = new Author();
        $author->setData('publicationId', $this->publicationId);
        $author->setData('email', $this->contributor['contact']);
        $author->setGivenName($creatorGivenName, $this->locale);
        $author->setFamilyName($creatorFamilyName, $this->locale);
        $author->setAffiliation("Harvard University", $this->locale);

        $authorDao->insertObject($author);

        return [new AuthorAdapter($creatorFullname, "Harvard University", $this->contributor['contact'])];
    }

    private function addCurrentPublicationToSubmission(): void
    {
        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        $submission = $submissionDao->getById($this->submissionId);
        $submission->setData('currentPublicationId', $this->publicationId);
        $submissionDao->updateObject($submission);
    }

    private function createTestSubmission(): int
    {
        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        $submission = new Submission();
        $submission->setData('contextId', $this->contextId);
        $submission->setData('dateSubmitted', $this->dateSubmitted);
        $submission->setData('status', $this->statusCode);
        $submission->setData('locale', $this->locale);
        $submission->setData('dateLastActivity', $this->dateLastActivity);

        return $submissionDao->insertObject($submission);
    }

    private function createTestPublication(): int
    {
        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $publication = new Publication();
        $publication->setData('submissionId', $this->submissionId);
        $publication->setData('title', $this->title, $this->locale);
        $publication->setData('abstract', $this->description);
        $publication->setData('title', $this->title, $this->locale);
        $publication->setData('relationStatus', '1');
        $publication->setData('status', $this->statusCode);
        $publication->setData('keywords', $this->keywords);

        return $publicationDao->insertObject($publication);
    }

    private function createDefaultTestAtomEntry(): void
    {
        $datasetModel = new DatasetModel($this->title, $this->creator, $this->subject, $this->description, $this->contributor);
        $this->packageCreator->loadMetadata($datasetModel);
        $this->packageCreator->createAtomEntry();
    }

    private function createDefaultTestAtomEntryFromSubmission(): void
    {
        $datasetModel = DatasetBuilder::build($this->submissionAdapter);
        $this->packageCreator->loadMetadata($datasetModel);
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
            'atomEntryCreator' => $this->creator[0],
            'atomEntrySubject' => $this->subject[0],
            'atomEntryContributor' => $this->contributor['contact']
        );

        $this->assertEquals($expectedMetadata, $atomEntryMetadata);
    }

    public function testValidateAtomEntryFromSubmissionRequiredMetadata(): void
    {
        $this->createDefaultTestAtomEntryFromSubmission();

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
            'atomEntryCreator' => $this->creator[0],
            'atomEntrySubject' => $this->subject[0],
            'atomEntryContributor' => $this->contributor['contact']
        );

        $this->assertEquals($expectedMetadata, $atomEntryMetadata);
    }

    public function testCreatePackageWithSampleFile()
    {
        $this->createDefaultTestAtomEntry();

        $this->packageCreator->addFileToPackage(dirname(__FILE__) . '/assets/testSample.csv', "sampleFileForTests.csv");
        $this->packageCreator->createPackage();

        $this->assertTrue(file_exists($this->packageCreator->getPackageFilePath()));
    }

    public function testCreatePackageFromSubmissionWithSampleFile()
    {
        $this->createDefaultTestAtomEntryFromSubmission();

        $this->packageCreator->addFileToPackage(dirname(__FILE__) . '/assets/testSample.csv', "sampleFileForTests.csv");
        $this->packageCreator->createPackage();

        $this->assertTrue(file_exists($this->packageCreator->getPackageFilePath()));
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
}
