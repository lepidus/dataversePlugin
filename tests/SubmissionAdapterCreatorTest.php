<?php

import('lib.pkp.tests.DatabaseTestCase');
import('plugins.generic.dataverse.classes.creators.SubmissionAdapterCreator');

class SubmissionAdapterCreatorTest extends DatabaseTestCase
{
    private $submissionAdapterCreator;
    private $submissionAdapter;

    private $contextId = 1;
    private $publicationId;
    private $submissionId;

    private $submissionTitle = "The Rise of The Machine Empire";
    private $authors = array();
    private $description = "This is an abstract / description";
    private $keywords = ["en_US" => array("computer science", "testing")];
    private $locale = 'en_US';

    private $dateSubmitted = '2021-05-31 15:38:24';
    private $statusCode = "STATUS_PUBLISHED";
    private $dateLastActivity = '2021-06-03 16:00:00';
    private $submissionAuthors;
    private $doi = "10.666/949494";

    public function setUp(): void
    {
        parent::setUp();
        $this->submissionAdapterCreator = new SubmissionAdapterCreator();
        $this->submissionId = $this->createTestSubmission();
        $this->publicationId = $this->createTestPublication();
        $this->authors = $this->createAuthors();
        $this->addCurrentPublicationToSubmission();
        $submissionSubjectDao = DAORegistry::getDAO('SubmissionSubjectDAO');
        $submissionSubjectDao->insertSubjects($this->keywords, $this->publicationId);

        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $publication = $publicationDao->getById($this->publicationId);
        $publication->setData('subjects', $submissionSubjectDao->getSubjects($publication->getId()));

        $publicationDao->updateObject($publication);

        $this->submissionAdapter = $this->submissionAdapterCreator->createSubmissionAdapter($this->submissionId, $this->authors);
    }

    protected function getAffectedTables()
    {
        return ['authors', 'submissions', 'publications', 'publication_settings', 'author_settings'];
    }

    private function createAuthors(): array
    {
        $authorFullName = 'Ana Alice Caldas Novas';
        $authorAffiliation = 'Harvard University';
        $authorEmail = 'anaalice@harvard.com';

        return [new AuthorAdapter($authorFullName, $authorAffiliation, $authorEmail)];
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
        $submission->setSubject('assunto da submissao', $this->locale);

        return $submissionDao->insertObject($submission);
    }

    private function createTestPublication(): int
    {
        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $publication = new Publication();
        $publication->setData('submissionId', $this->submissionId);
        $publication->setData('title', $this->submissionTitle, $this->locale);
        $publication->setData('abstract', $this->description);
        $publication->setData('title', $this->submissionTitle, $this->locale);
        $publication->setData('relationStatus', '1');
        $publication->setData('status', $this->statusCode);
        $publication->setData('keywords', $this->keywords);

        return $publicationDao->insertObject($publication);
    }

    public function testCreatorReturnsSubmissionAdapterObject(): void
    {
        $this->assertTrue($this->submissionAdapter instanceof SubmissionAdapter);
    }

    public function testRetrieveSubmissionTitle(): void
    {
        $this->assertEquals($this->submissionTitle, $this->submissionAdapter->getTitle());
    }

    public function testRetrieveSubmissionAuthors(): void
    {
        $this->assertEquals($this->authors, $this->submissionAdapter->getAuthors());
    }

    public function testRetrieveSubmissionDescription(): void
    {
        $this->assertEquals($this->description, $this->submissionAdapter->getDescription());
    }
    
    public function testRetrieveSubmissionKeywords(): void
    {
        $this->assertEquals($this->keywords[$this->locale], $this->submissionAdapter->getKeywords());
    }
}
