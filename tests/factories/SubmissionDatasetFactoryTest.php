<?php

use PKP\tests\PKPTestCase;
use APP\submission\Submission;
use APP\publication\Publication;
use APP\author\Author;
use PKP\user\User;
use APP\core\Request;
use PKP\core\Registry;
use APP\journal\Journal;
use APP\journal\JournalDAO;
use PKP\file\TemporaryFile;
use PKP\file\TemporaryFileDAO;
use PKP\db\DAORegistry;
use PKP\services\PKPSchemaService;
use Illuminate\Support\LazyCollection;
use APP\plugins\generic\dataverse\classes\APACitation;
use APP\plugins\generic\dataverse\classes\entities\Dataset;
use APP\plugins\generic\dataverse\classes\entities\DatasetContact;
use APP\plugins\generic\dataverse\classes\entities\DatasetAuthor;
use APP\plugins\generic\dataverse\classes\entities\DatasetFile;
use APP\plugins\generic\dataverse\classes\draftDatasetFile\DraftDatasetFile;
use APP\plugins\generic\dataverse\classes\draftDatasetFile\DAO as DraftDatasetFileDao;
use APP\plugins\generic\dataverse\classes\draftDatasetFile\Repository as DraftDatasetFileRepo;
use APP\plugins\generic\dataverse\classes\factories\SubmissionDatasetFactory;

class SubmissionDatasetFactoryTest extends PKPTestCase
{
    private $submission;
    private $publication;
    private $author;
    private $locale;
    private $user;
    private $journal;
    private $temporaryFile;
    private $draftDatasetFile;
    private $mockDraftDatasetFileRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->locale = 'en';
        $this->submission = $this->createTestSubmission();

        $this->registerMockRequest();
        $this->registerMockJournalDAO();
        $this->registerMockTemporaryFileDAO();
        $this->mockDraftDatasetFileRepo = $this->createMockDraftDatasetFileRepo();
    }

    protected function getMockedRegistryKeys(): array
    {
        return ['request'];
    }

    protected function getMockedDAOs(): array
    {
        return ['JournalDAO', 'TemporaryFileDAO'];
    }

    private function registerMockRequest(): void
    {
        $this->user = new User();
        $this->user->setId(rand());
        $this->user->setGivenName('John', $this->locale);
        $this->user->setFamilyName('Doe', $this->locale);

        $mockRequest = $this->getMockBuilder(Request::class)
            ->setMethods(['getUser'])
            ->getMock();
        $mockRequest->expects($this->any())
                    ->method('getUser')
                    ->will($this->returnValue($this->user));
        Registry::set('request', $mockRequest);
    }

    private function registerMockJournalDAO(): void
    {
        $journalDAO = $this->getMockBuilder(JournalDAO::class)
            ->setMethods(['getById'])
            ->getMock();

        $this->journal = new Journal();
        $this->journal->setPrimaryLocale($this->locale);
        $this->journal->setName('Dataverse Preprints', $this->locale);

        $journalDAO->expects($this->any())
            ->method('getById')
            ->will($this->returnValue($this->journal));

        DAORegistry::registerDAO('JournalDAO', $journalDAO);
    }

    private function createMockDraftDatasetFileRepo()
    {
        $schemaService = new PKPSchemaService();
        $draftDatasetFileDao = new DraftDatasetFileDao($schemaService);
        $draftDatasetFileRepo = $this->getMockBuilder(DraftDatasetFileRepo::class)
            ->setConstructorArgs([$draftDatasetFileDao])
            ->setMethods(['getBySubmissionId'])
            ->getMock();

        $draftDatasetFile = new DraftDatasetFile();
        $draftDatasetFile->setId(rand());
        $draftDatasetFile->setData('submissionId', $this->submission->getId());
        $draftDatasetFile->setData('userId', $this->user->getId());
        $draftDatasetFile->setData('fileId', rand());
        $draftDatasetFile->setData('fileName', 'test.pdf');
        $this->draftDatasetFile = $draftDatasetFile;

        $collectionFiles = LazyCollection::make(function () use ($draftDatasetFile) {
            yield $draftDatasetFile->getId() => $draftDatasetFile;
        });

        $draftDatasetFileRepo->expects($this->any())
            ->method('getBySubmissionId')
            ->will($this->returnValue($collectionFiles));

        return $draftDatasetFileRepo;
    }

    private function registerMockTemporaryFileDAO(): void
    {
        $temporaryFileDAO = $this->getMockBuilder(TemporaryFileDAO::class)
            ->setMethods(['getTemporaryFile'])
            ->getMock();

        $this->temporaryFile = new TemporaryFile();
        $this->temporaryFile->setId(rand());
        $this->temporaryFile->setServerFileName('sample.pdf');
        $this->temporaryFile->setOriginalFileName('sample.pdf');

        $temporaryFileDAO->expects($this->any())
            ->method('getTemporaryFile')
            ->will($this->returnValue($this->temporaryFile));

        DAORegistry::registerDAO('TemporaryFileDAO', $temporaryFileDAO);
    }

    private function createTestSubmission(): Submission
    {
        $submission = new Submission();
        $submission->setId(rand());
        $submission->setData('contextId', rand());
        $submission->setData('dateSubmitted', '2021-01-01 15:00:00');
        $submission->setData('locale', $this->locale);
        $submission->setData('datasetSubject', 'Other');
        $submission->setData('datasetLicense', 'CC BY 4.0');

        $author = new Author();
        $author->setId(rand());
        $author->setGivenName('Iris', $this->locale);
        $author->setFamilyName('Castanheiras', $this->locale);
        $author->setEmail('iris@testmail.com');
        $author->setAffiliation('Dataverse', $this->locale);
        $author->setOrcid('https://orcid.org/0000-0000-0000-0000');

        $collectionAuthors = LazyCollection::make(function () use ($author) {
            yield $author->getId() => $author;
        });

        $publication = new Publication();
        $publication->setId(rand());
        $publication->setData('locale', $this->locale);
        $publication->setData('title', 'The Rise of The Machine Empire');
        $publication->setData('abstract', 'An example abstract');
        $publication->setData('keywords', ['Modern History'], $this->locale);

        $author->setData('publicationId', $publication->getId());
        $publication->setData('submissionId', $submission->getId());
        $publication->setData('primaryContactId', $author->getId());
        $publication->setData('authors', $collectionAuthors);
        $submission->setData('currentPublicationId', $publication->getId());
        $submission->setData('publications', [$publication]);

        $this->author = $author;
        $this->publication = $publication;

        return $submission;
    }

    public function testFactoryCreateDatasetFromSubmission(): void
    {
        $factory = new SubmissionDatasetFactory($this->submission);
        $factory->setDraftDatasetFileRepo($this->mockDraftDatasetFileRepo);
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

        $apaCitation = new APACitation();
        $datasetPubCitation = $apaCitation->getFormattedCitationBySubmission($this->submission);

        $datasetFile = new DatasetFile();
        $datasetFile->setOriginalFileName($this->temporaryFile->getOriginalFileName());
        $datasetFile->setPath($this->temporaryFile->getFilePath());

        $expectedDataset = new Dataset();
        $datasetTitlePrefix = __('plugins.generic.dataverse.dataset.titlePrefix');
        $expectedDataset->setTitle($datasetTitlePrefix . $this->publication->getLocalizedTitle());
        $expectedDataset->setDescription($this->publication->getLocalizedData('abstract'));
        $expectedDataset->setKeywords($this->publication->getLocalizedData('keywords'));
        $expectedDataset->setSubject($this->submission->getData('datasetSubject'));
        $expectedDataset->setLicense($this->submission->getData('datasetLicense'));
        $expectedDataset->setAuthors([$this->author->getId() => $datasetAuthor]);
        $expectedDataset->setContact($datasetContact);
        $expectedDataset->setDepositor($datasetDepositor);
        $expectedDataset->setPubCitation($datasetPubCitation);
        $expectedDataset->setFiles([$this->draftDatasetFile->getId() => $datasetFile]);

        $this->assertEquals($expectedDataset, $dataset);
    }
}
