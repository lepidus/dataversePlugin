<?php

import('lib.pkp.tests.PKPTestCase');
import('classes.journal.Journal');
import('classes.submission.Submission');
import('classes.publication.Publication');
import('classes.article.Author');
import('plugins.generic.dataverse.classes.creators.SubmissionAdapterCreator');

class SubmissionAdapterCreatorTest extends PKPTestCase
{
    private $submissionAdapterCreator;
    private $submissionAdapter;
    private $journal;

    private $contextId = 1;
    private $submissionId = 1245;
    private $publicationId = 1234;

    private $title = "The Rise of The Machine Empire";
    private $authors = array();
    private $description = "This is an abstract / description";
    private $keywords = ["en_US" => array("computer science", "testing")];
    private $locale = 'en_US';

    private $dateSubmitted = '2021-05-31 15:38:24';
    private $statusCode = "STATUS_PUBLISHED";
    private $dateLastActivity = '2021-06-03 16:00:00';
    private $submissionAuthors;

    public function setUp(): void
    {
        parent::setUp();
        $this->submissionAdapterCreator = new SubmissionAdapterCreator();

        $this->createTestJournal();
        $this->createTestSubmission();
        $this->createAuthors();
        $this->createTestPublication();
        $this->addCurrentPublicationToSubmission();

        $this->submissionAdapter = $this->submissionAdapterCreator->createSubmissionAdapter($this->submission, $this->locale);
    }

    private function createAuthors(): void
    {
        $author = new Author();
        $author->setData('publicationId', $this->publicationId);
        $author->setData('email', 'anaalice@harvard.com');
        $author->setGivenName('Ana Alice', $this->locale);
        $author->setFamilyName('Caldas Novas', $this->locale);
        $author->setAffiliation("Harvard University", $this->locale);

        $this->authors = [$author];

        $this->submissionAuthors = [
            new AuthorAdapter(
                $author->getLocalizedGivenName($this->locale),
                $author->getLocalizedFamilyName($this->locale),
                $author->getLocalizedData('affiliation', $this->locale),
                $author->getData('email')
        )];
    }

    private function addCurrentPublicationToSubmission(): void
    {
        $this->submission->setData('currentPublicationId', $this->publicationId);
        $this->submission->setData('publications', array($this->publication));
    }

    private function createTestSubmission(): void
    {
        $this->submission = new Submission();
        $this->submission->setId($this->submissionId);
        $this->submission->setData('contextId', $this->contextId);
        $this->submission->setData('dateSubmitted', $this->dateSubmitted);
        $this->submission->setData('status', $this->statusCode);
        $this->submission->setData('locale', $this->locale);
        $this->submission->setData('dateLastActivity', $this->dateLastActivity);
    }

    private function createTestPublication(): void
    {
        $this->publication = new Publication();
        $this->publication->setId($this->publicationId);
        $this->publication->setData('submissionId', $this->submissionId);
        $this->publication->setData('title', $this->title, $this->locale);
        $this->publication->setData('abstract', $this->description);
        $this->publication->setData('authors', $this->authors);
        $this->publication->setData('locale', $this->locale);
        $this->publication->setData('relationStatus', '1');
        $this->publication->setData('status', $this->statusCode);
        $this->publication->setData('keywords', $this->keywords);
    }

    private function createTestJournal(): void
    {
        $this->journal = new Journal();
        $this->journal->setPrimaryLocale($this->locale);
        $this->journal->setName('Preprints da Lepidus', $this->locale);
    }

    public function testCreatorReturnsSubmissionAdapterObject(): void
    {
        $this->assertTrue($this->submissionAdapter instanceof SubmissionAdapter);
    }

    public function testRetrieveSubmissionTitle(): void
    {
        $this->assertEquals($this->title, $this->submissionAdapter->getTitle());
    }

    public function testAuthorsCitationIsAPA(): void
    {
        $firstAuthor = new AuthorAdapter('Ana Alice', 'Caldas Novas', "", "");
        $secondAuthor = new AuthorAdapter('Deane', 'Chord', "", "");
        $thirdAuthor = new AuthorAdapter('Francis', 'Bucker', "", "");

        $authors = [$firstAuthor, $secondAuthor, $thirdAuthor];

        $submissionAdapterCreator = new SubmissionAdapterCreator();

        $resultCitation = $submissionAdapterCreator->createAuthorsCitationAPA($authors);
        $expectedCitation = 'Caldas Novas, A., Chord, D., &amp; Bucker, F.';

        $this->assertEquals($resultCitation, $expectedCitation);
    }

    public function testVariousAuthorsCitationIsAPA(): void
    {
        $firstAuthor = new AuthorAdapter('Deane', 'Chord', "", "");
        $secondAuthor = new AuthorAdapter('Ana Alice', 'Caldas Novas', "", "");
        $thirdAuthor = new AuthorAdapter('Íris', 'Castanheiras', "", "");
        $fourthAuthor = new AuthorAdapter('Francis', 'Bucker', "", "");
        $fifthAuthor = new AuthorAdapter('Tim', 'Winter', "", "");
        $sixthAuthor = new AuthorAdapter('Walter', 'Zappy', "", "");

        $authors = [$firstAuthor, $secondAuthor, $thirdAuthor, $fourthAuthor, $fifthAuthor, $sixthAuthor];

        $submissionAdapterCreator = new SubmissionAdapterCreator();

        $resultCitation = $submissionAdapterCreator->createAuthorsCitationAPA($authors);
        $expectedCitation = 'Chord, D. et al.';

        $this->assertEquals($resultCitation, $expectedCitation);
    }

    public function testSubmissionCitationIsAPA(): void
    {
        $submissionAdapterCreator = new SubmissionAdapterCreator();

        $resultCitation = $submissionAdapterCreator->createSubmissionCitationAPA($this->submission, $this->journal);

        $expectedCitation = 'Caldas Novas, A. (2021). <em>The Rise of The Machine Empire</em>. Preprints da Lepidus';

        $this->assertEquals($resultCitation, $expectedCitation);
    }

    public function testRetrieveSubmissionAuthors(): void
    {
        $this->assertEquals($this->submissionAuthors, $this->submissionAdapter->getAuthors());
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
