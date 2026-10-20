<?php

use APP\core\Application;
use APP\core\Request as PKPRequest;
use APP\plugins\generic\dataverse\api\v1\datasets\DatasetHandler;
use APP\plugins\generic\dataverse\classes\DraftDatasetFilesValidator;
use APP\plugins\generic\dataverse\classes\dataverseStudy\DataverseStudy;
use APP\plugins\generic\dataverse\classes\dataverseStudy\Repository as DataverseStudyRepository;
use APP\plugins\generic\dataverse\classes\draftDatasetFile\Repository as DraftDatasetFileRepository;
use APP\plugins\generic\dataverse\classes\entities\Dataset;
use APP\plugins\generic\dataverse\classes\entities\DatasetFile;
use APP\plugins\generic\dataverse\classes\entities\DatasetRelatedPublication;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\classes\factories\SubmissionDatasetFactory;
use APP\plugins\generic\dataverse\classes\services\DatasetFileService;
use APP\plugins\generic\dataverse\classes\services\DatasetService;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DataverseCollectionActions;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use APP\submission\Submission;
use Illuminate\Support\LazyCollection;
use PKP\core\APIResponse;
use PKP\core\Dispatcher;
use PKP\core\Registry;
use PKP\facades\Locale;
use PKP\security\Role;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\tests\PKPTestCase;
use PKP\user\User;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @runTestsInSeparateProcesses
 *
 * @preserveGlobalState disabled
 */
class DatasetHandlerAccessTest extends PKPTestCase
{
    private const STUDY_ID = 7;
    private const DATASET_FILE_ID = 2025434;
    private const OTHER_DATASET_FILE_ID = 9999999;

    private Submission $submission;
    private DataverseStudy $study;

    protected function getMockedRegistryKeys(): array
    {
        return ['request'];
    }

    protected function getMockedContainerKeys(): array
    {
        return [DataverseStudyRepository::class, DraftDatasetFileRepository::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        ini_set('error_log', '/dev/null');

        $this->submission = new Submission();
        $this->submission->setId(23);
        $this->submission->setData('selectedDataFilesForReview', [self::DATASET_FILE_ID]);

        $this->study = new DataverseStudy();
        $this->study->setId(self::STUDY_ID);
        $this->study->setSubmissionId($this->submission->getId());
        $this->study->setPersistentId('doi:10.5072/FK2/TEST');

        $studyRepository = Mockery::mock(DataverseStudyRepository::class);
        $studyRepository->shouldReceive('get')->with(self::STUDY_ID)->andReturn($this->study);
        app()->instance(DataverseStudyRepository::class, $studyRepository);

        $this->mockContextRequest(false);
    }

    private function mockContextRequest(bool $restrictReviewerFileAccess): void
    {
        $context = Application::getContextDAO()->newDataObject();
        $context->setId(1);
        $context->setData('path', 'test');
        $context->setData('supportedSubmissionLocales', ['en']);
        $context->setData('restrictReviewerFileAccess', $restrictReviewerFileAccess);
        $dispatcher = $this->createMock(Dispatcher::class);
        $dispatcher->method('url')->willReturn('https://example.org/api/v1/vocabs');
        $pkpRequest = $this->createMock(PKPRequest::class);
        $pkpRequest->method('getContext')->willReturn($context);
        $pkpRequest->method('getDispatcher')->willReturn($dispatcher);
        $user = new User();
        $user->setId(5);
        $pkpRequest->method('getUser')->willReturn($user);
        Registry::set('request', $pkpRequest);
    }

    private function createDatasetFile(int $id, string $fileName): DatasetFile
    {
        $datasetFile = new DatasetFile();
        $datasetFile->setId($id);
        $datasetFile->setFileName($fileName);

        return $datasetFile;
    }

    private function mockDatasetFileActions(): Mockery\MockInterface
    {
        $fileActions = Mockery::mock();
        $fileActions->shouldReceive('getByDatasetId')
            ->with($this->study->getPersistentId())
            ->andReturn([$this->createDatasetFile(self::DATASET_FILE_ID, 'Data.csv')]);

        $client = Mockery::mock('overload:' . DataverseClient::class);
        $client->shouldReceive('getDatasetFileActions')->andReturn($fileActions);

        return $fileActions;
    }

    private function createRequest(array $queryParams = [], array $body = []): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getQueryParams')->willReturn($queryParams);
        $request->method('getParsedBody')->willReturn($body);

        return $request;
    }

    private function createHandler(
        ?ReviewAssignment $reviewAssignment = null,
        array $userRoles = [Role::ROLE_ID_MANAGER],
        bool $isAssignedToSubmission = false
    ): DatasetHandler {
        $authorizedObjects = [
            Application::ASSOC_TYPE_SUBMISSION => $this->submission,
            Application::ASSOC_TYPE_REVIEW_ASSIGNMENT => $reviewAssignment,
            Application::ASSOC_TYPE_USER_ROLES => $userRoles,
        ];
        $handler = $this->getMockBuilder(DatasetHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAuthorizedContextObject', 'isAssignedToSubmission'])
            ->getMock();
        $handler->method('getAuthorizedContextObject')
            ->willReturnCallback(function (int $assocType) use ($authorizedObjects) {
                return $authorizedObjects[$assocType] ?? null;
            });
        $handler->method('isAssignedToSubmission')->willReturn($isAssignedToSubmission);

        return $handler;
    }

    private function createReviewAssignment(bool $accepted): ReviewAssignment
    {
        $reviewAssignment = new ReviewAssignment();
        $reviewAssignment->setDateConfirmed($accepted ? '2026-10-01 10:00:00' : null);

        return $reviewAssignment;
    }

    public function testStudyOfAnotherSubmissionIsNotFound(): void
    {
        $this->study->setSubmissionId(99);

        $response = $this->createHandler()->get($this->createRequest(), new APIResponse(), ['studyId' => self::STUDY_ID]);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testDownloadRejectsFilesOutsideTheStudyDataset(): void
    {
        $fileActions = $this->mockDatasetFileActions();
        $fileActions->shouldNotReceive('download');

        $response = $this->createHandler()->downloadDatasetFile(
            $this->createRequest(['fileId' => self::OTHER_DATASET_FILE_ID]),
            new APIResponse(),
            ['studyId' => self::STUDY_ID]
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testDeleteFileRejectsFilesOutsideTheStudyDataset(): void
    {
        $this->mockDatasetFileActions();
        Mockery::mock('overload:' . DatasetFileService::class)->shouldNotReceive('delete');

        $response = $this->createHandler()->deleteFile(
            $this->createRequest(['fileId' => self::OTHER_DATASET_FILE_ID, 'fileName' => 'Other.csv']),
            new APIResponse(),
            ['studyId' => self::STUDY_ID]
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testDeleteFileLogsTheNameKnownByDataverse(): void
    {
        $this->mockDatasetFileActions();
        Mockery::mock('overload:' . DatasetFileService::class)
            ->shouldReceive('delete')->once()->with($this->study, (string) self::DATASET_FILE_ID, 'Data.csv');

        $response = $this->createHandler()->deleteFile(
            $this->createRequest(['fileId' => self::DATASET_FILE_ID, 'fileName' => '<img src=x>']),
            new APIResponse(),
            ['studyId' => self::STUDY_ID]
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testReviewerCannotDownloadFilesNotSelectedForReview(): void
    {
        $this->submission->setData('selectedDataFilesForReview', []);
        $fileActions = $this->mockDatasetFileActions();
        $fileActions->shouldNotReceive('download');

        $response = $this->createHandler($this->createReviewAssignment(true), [Role::ROLE_ID_REVIEWER])
            ->downloadDatasetFile(
                $this->createRequest(['fileId' => self::DATASET_FILE_ID]),
                new APIResponse(),
                ['studyId' => self::STUDY_ID]
            );

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testReviewerCannotDownloadBeforeAcceptingWhenFileAccessIsRestricted(): void
    {
        $this->mockContextRequest(true);
        $fileActions = $this->mockDatasetFileActions();
        $fileActions->shouldNotReceive('download');

        $response = $this->createHandler($this->createReviewAssignment(false), [Role::ROLE_ID_REVIEWER])
            ->downloadDatasetFile(
                $this->createRequest(['fileId' => self::DATASET_FILE_ID]),
                new APIResponse(),
                ['studyId' => self::STUDY_ID]
            );

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testEditorWhoIsAlsoReviewerDownloadsAnyFileOfTheDataset(): void
    {
        $this->submission->setData('selectedDataFilesForReview', []);
        $fileActions = $this->mockDatasetFileActions();
        $fileActions->shouldReceive('download')->once()->with(self::DATASET_FILE_ID, 'Data.csv');

        $response = $this->createHandler(
            $this->createReviewAssignment(true),
            [Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_REVIEWER],
            true
        )->downloadDatasetFile(
            $this->createRequest(['fileId' => self::DATASET_FILE_ID]),
            new APIResponse(),
            ['studyId' => self::STUDY_ID]
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    private function mockDeposit(?DataverseException $requiredMetadataError = null): array
    {
        Locale::registerPath(dirname(__DIR__, 4) . '/locale');
        $draftRepository = Mockery::mock(DraftDatasetFileRepository::class);
        $draftRepository->shouldReceive('getBySubmissionId')->andReturn(LazyCollection::make(['README.txt', 'Data.csv']));
        app()->instance(DraftDatasetFileRepository::class, $draftRepository);
        $validator = Mockery::mock('overload:' . DraftDatasetFilesValidator::class);
        $validator->shouldReceive('datasetHasReadmeFile')->andReturn(true);
        $validator->shouldReceive('datasetHasNonReadmeFile')->andReturn(true);

        $datasetFiles = [$this->createDatasetFile(1, 'README.txt'), $this->createDatasetFile(2, 'Data.csv')];
        $dataset = new Dataset();
        $dataset->setFiles($datasetFiles);
        $dataset->setRelatedPublication(new DatasetRelatedPublication(null, 'Citation', null, null, null));
        Mockery::mock('overload:' . SubmissionDatasetFactory::class)
            ->shouldReceive('getDataset')->andReturn($dataset);

        $collectionActions = $this->getMockBuilder(DataverseCollectionActions::class)
            ->disableOriginalConstructor()->onlyMethods(['getRequiredMetadata', 'getLicenses'])->getMock();
        if ($requiredMetadataError) {
            $collectionActions->method('getRequiredMetadata')->willThrowException($requiredMetadataError);
        } else {
            $collectionActions->method('getRequiredMetadata')->willReturn(['citation' => ['fields' => []]]);
        }
        $collectionActions->method('getLicenses')->willReturn([['name' => 'CC0 1.0']]);
        Mockery::mock('overload:' . DataverseClient::class)
            ->shouldReceive('getDataverseCollectionActions')->andReturn($collectionActions);

        return $datasetFiles;
    }

    private function depositParams(array $extraParams = []): array
    {
        return array_merge([
            'datasetTitle' => 'Title',
            'datasetDescription' => 'Description',
            'datasetKeywords' => [Locale::getLocale() => ['Keyword']],
            'datasetLanguage' => 'English',
            'datasetSubject' => 'Other',
            'datasetLicense' => 'CC0 1.0',
            'datasetRelationType' => 'IsSupplementTo',
        ], $extraParams);
    }

    public function testAddIgnoresParamsThatAreNotMetadataFields(): void
    {
        $datasetFiles = $this->mockDeposit();
        Mockery::mock('overload:' . DatasetService::class)
            ->shouldReceive('deposit')->once()
            ->withArgs(function ($submission, Dataset $deposited) use ($datasetFiles) {
                return $deposited->getFiles() === $datasetFiles
                    && !$deposited->hasData('submissionId')
                    && !$deposited->hasData('persistentId');
            })
            ->andReturn(['status' => 'Success']);

        $response = $this->createHandler()->addDataset(
            $this->createRequest([], $this->depositParams([
                'datasetFiles' => [['path' => '/etc/passwd', 'originalFileName' => 'passwd']],
                'submissionId' => 99,
                'datasetPersistentId' => 'doi:10.5072/FK2/UNTRUSTED',
            ])),
            new APIResponse(),
            []
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testAddIsRefusedWhenTheCollectionRequiredMetadataCannotBeLoaded(): void
    {
        $this->mockDeposit(new DataverseException('Service unavailable', 503));
        Mockery::mock('overload:' . DatasetService::class)->shouldNotReceive('deposit');

        $response = $this->createHandler()->addDataset(
            $this->createRequest([], $this->depositParams(['datasetAlternativeURL' => 'https://example.org'])),
            new APIResponse(),
            []
        );

        $this->assertSame(503, $response->getStatusCode());
    }
}
