<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.entities.DatasetContact');
import('plugins.generic.dataverse.classes.factories.dataset.SubmissionDatasetFactory');

class SubmissionDatasetFactoryTest extends PKPTestCase
{
    private $submission;

    private $publication;

    private $authors;

    private $locale;

    private $user;

    private $journal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->locale = 'en_US';
        $this->submission = $this->createTestSubmission();

        $this->registerMockRequest();
        $this->registerMockJournalDAO();
    }

    protected function getMockedRegistryKeys(): array
    {
        return ['request'];
    }

    protected function getMockedDAOs(): array
    {
        return ['JournalDAO'];
    }

    private function registerMockRequest(): void
    {
        import('lib.pkp.classes.user.User');
        $this->user = new User();
        $this->user->setGivenName('John', $this->locale);
        $this->user->setFamilyName('Doe', $this->locale);

        $mockRequest = $this->getMockBuilder(Request::class)
            ->setMethods(array('getUser'))
            ->getMock();
        $mockRequest->expects($this->any())
                    ->method('getUser')
                    ->will($this->returnValue($this->user));
        Registry::set('request', $mockRequest);
    }

    private function registerMockJournalDAO(): void
    {
        $journalDAO = $this->getMockBuilder(JournalDAO::class)
            ->setMethods(array('getById'))
            ->getMock();

        import('classes.journal.Journal');
        $this->journal = new Journal();
        $this->journal->setPrimaryLocale($this->locale);
        $this->journal->setName('Dataverse Preprints', $this->locale);

        $journalDAO->expects($this->any())
                   ->method('getById')
                   ->will($this->returnValue($this->journal));

        DAORegistry::registerDAO('JournalDAO', $journalDAO);
    }

    private function createTestSubmission(): Submission
    {
        import('classes.submission.Submission');
        $submission = new Submission();
        $submission->setId(rand());
        $submission->setData('contextId', rand());
        $submission->setData('dateSubmitted', '2021-01-01 15:00:00');
        $submission->setData('locale', $this->locale);
        $submission->setData('datasetSubject', 'Other');

        import('classes.article.Author');
        $author = new Author();
        $author->setGivenName('Iris', $this->locale);
        $author->setFamilyName('Castanheiras', $this->locale);
        $author->setEmail('iris@testmail.com');
        $author->setAffiliation('Dataverse', $this->locale);
        $author->setOrcid('https://orcid.org/0000-0000-0000-0000');

        import('classes.publication.Publication');
        $publication = new Publication();
        $publication->setId(rand());
        $publication->setData('locale', $this->locale);
        $publication->setData('title', 'The Rise of The Machine Empire');
        $publication->setData('abstract', 'An example abstract');
        $publication->setData('keywords', ['Modern History']);

        $author->setData('publicationId', $publication->getId());
        $publication->setData('submissionId', $submission->getId());
        $publication->setData('authors', [$author]);
        $submission->setData('currentPublicationId', $publication->getId());
        $submission->setData('publications', [$publication]);

        $this->author = $author;
        $this->publication = $publication;

        return $submission;
    }

    public function testFactoryCreateDatasetFromSubmission(): void
    {
        $factory = new SubmissionDatasetFactory($this->submission);
        $dataset = $factory->getDataset();

        $datasetAuthor = new DatasetAuthor(
            $this->author->getFullName(false, true),
            $this->author->getLocalizedData('affiliation'),
            explode('https://orcid.org/', $this->author->getOrcid())[1]
        );
        $datasetContact = new DatasetContact(
            $this->author->getFullName(false, true),
            $this->author->getEmail(),
            $this->author->getLocalizedData('affiliation')
        );
        $datasetDepositor = $this->user->getFullName(false, true)
        . ' (via ' . $this->journal->getLocalizedName() . ')';

        import('plugins.generic.dataverse.classes.APACitation');
        $apaCitation = new APACitation();
        $datasetPubCitation = $apaCitation->getFormattedCitationBySubmission($this->submission);

        $expectedDataset = new Dataset();
        $expectedDataset->setTitle($this->publication->getLocalizedTitle());
        $expectedDataset->setDescription($this->publication->getLocalizedData('abstract'));
        $expectedDataset->setKeywords($this->publication->getData('keywords'));
        $expectedDataset->setSubject($this->submission->getData('datasetSubject'));
        $expectedDataset->setAuthors([$datasetAuthor]);
        $expectedDataset->setContact($datasetContact);
        $expectedDataset->setDepositor($datasetDepositor);
        $expectedDataset->setPubCitation($datasetPubCitation);

        $this->assertEquals($expectedDataset, $dataset);
    }
}
