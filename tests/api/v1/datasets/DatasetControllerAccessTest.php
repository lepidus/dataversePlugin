<?php

use APP\core\Application;
use APP\core\Request as PKPRequest;
use APP\plugins\generic\dataverse\api\v1\datasets\DatasetController;
use APP\plugins\generic\dataverse\classes\DraftDatasetFilesValidator;
use APP\plugins\generic\dataverse\classes\dataverseStudy\DataverseStudy;
use APP\plugins\generic\dataverse\classes\dataverseStudy\Repository as DataverseStudyRepository;
use APP\plugins\generic\dataverse\classes\draftDatasetFile\Repository as DraftDatasetFileRepository;
use APP\plugins\generic\dataverse\classes\entities\Dataset;
use APP\plugins\generic\dataverse\classes\entities\DatasetFile;
use APP\plugins\generic\dataverse\classes\entities\DatasetRelatedPublication;
use APP\plugins\generic\dataverse\classes\factories\SubmissionDatasetFactory;
use APP\plugins\generic\dataverse\classes\services\DatasetFileService;
use APP\plugins\generic\dataverse\classes\services\DatasetService;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DataverseCollectionActions;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use APP\submission\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\LazyCollection;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PKP\core\Dispatcher;
use PKP\core\Registry;
use PKP\facades\Locale;
use PKP\security\Role;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\tests\PKPTestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class DatasetControllerAccessTest extends PKPTestCase
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

    private function createRequest(string $method, string $uri, array $params = []): Request
    {
        $request = Request::create($uri, $method, $params);
        $route = new Illuminate\Routing\Route($method, 'datasets/{studyId}/{action}', []);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    private function createController(?ReviewAssignment $reviewAssignment = null, array $userRoles = [Role::ROLE_ID_MANAGER]): DatasetController
    {
        $authorizedObjects = [
            Application::ASSOC_TYPE_SUBMISSION => $this->submission,
            Application::ASSOC_TYPE_REVIEW_ASSIGNMENT => $reviewAssignment,
            Application::ASSOC_TYPE_USER_ROLES => $userRoles,
        ];
        $controller = $this->getMockBuilder(DatasetController::class)
            ->onlyMethods(['getAuthorizedContextObject'])
            ->getMock();
        $controller->method('getAuthorizedContextObject')
            ->willReturnCallback(fn (int $assocType) => $authorizedObjects[$assocType] ?? null);

        return $controller;
    }

    private function createReviewAssignment(bool $accepted): ReviewAssignment
    {
        $reviewAssignment = new ReviewAssignment();
        $reviewAssignment->setDateConfirmed($accepted ? '2026-10-01 10:00:00' : null);

        return $reviewAssignment;
    }

    public function testDownloadFileRejectsFilesOutsideTheStudyDataset(): void
    {
        $fileActions = $this->mockDatasetFileActions();
        $fileActions->shouldNotReceive('download');

        $request = $this->createRequest('GET', '/datasets/' . self::STUDY_ID . '/file', [
            'fileId' => self::OTHER_DATASET_FILE_ID,
            'fileName' => 'Other.csv',
        ]);

        $this->assertSame(404, $this->createController()->downloadFile($request)->getStatusCode());
    }

    public function testDeleteFileRejectsFilesOutsideTheStudyDataset(): void
    {
        $this->mockDatasetFileActions();
        $service = Mockery::mock('overload:' . DatasetFileService::class);
        $service->shouldNotReceive('delete');

        $request = $this->createRequest('DELETE', '/datasets/' . self::STUDY_ID . '/file?fileId=' . self::OTHER_DATASET_FILE_ID . '&fileName=Other.csv');

        $this->assertSame(404, $this->createController()->deleteFile($request)->getStatusCode());
    }

    public function testDeleteFileLogsTheNameKnownByDataverse(): void
    {
        $this->mockDatasetFileActions();
        $service = Mockery::mock('overload:' . DatasetFileService::class);
        $service->shouldReceive('delete')->once()->with($this->study, (string) self::DATASET_FILE_ID, 'Data.csv');

        $request = $this->createRequest('DELETE', '/datasets/' . self::STUDY_ID . '/file?fileId=' . self::DATASET_FILE_ID . '&fileName=<img src=x>');

        $this->assertSame(200, $this->createController()->deleteFile($request)->getStatusCode());
    }

    public function testReviewerCannotDownloadFilesNotSelectedForReview(): void
    {
        $this->submission->setData('selectedDataFilesForReview', []);
        $fileActions = $this->mockDatasetFileActions();
        $fileActions->shouldNotReceive('download');

        $request = $this->createRequest('GET', '/datasets/' . self::STUDY_ID . '/file', ['fileId' => self::DATASET_FILE_ID]);
        $controller = $this->createController($this->createReviewAssignment(true), [Role::ROLE_ID_REVIEWER]);

        $this->assertSame(403, $controller->downloadFile($request)->getStatusCode());
    }

    public function testReviewerCannotDownloadBeforeAcceptingWhenFileAccessIsRestricted(): void
    {
        $this->mockContextRequest(true);
        $fileActions = $this->mockDatasetFileActions();
        $fileActions->shouldNotReceive('download');

        $request = $this->createRequest('GET', '/datasets/' . self::STUDY_ID . '/file', ['fileId' => self::DATASET_FILE_ID]);
        $controller = $this->createController($this->createReviewAssignment(false), [Role::ROLE_ID_REVIEWER]);

        $this->assertSame(403, $controller->downloadFile($request)->getStatusCode());
    }

    public function testInReviewChecksTheLocksOfTheStudyDataset(): void
    {
        $dataset = new Dataset();
        $dataset->setDatasetId(77);
        $datasetActions = Mockery::mock();
        $datasetActions->shouldReceive('get')->with($this->study->getPersistentId())->andReturn($dataset);
        $datasetActions->shouldReceive('getDatasetLocks')->once()->with(77)->andReturn([['lockType' => 'InReview']]);
        $client = Mockery::mock('overload:' . DataverseClient::class);
        $client->shouldReceive('getDatasetActions')->andReturn($datasetActions);

        $request = $this->createRequest('GET', '/datasets/' . self::STUDY_ID . '/inReview', ['datasetId' => 12345]);

        $response = $this->createController()->getInReview($request);

        $this->assertSame(['inReview' => true], $response->getData(true));
    }

    public function testAddIgnoresParamsThatAreNotMetadataFields(): void
    {
        $draftFiles = ['readme' => 'README.txt', 'data' => 'Data.csv'];
        $draftRepository = Mockery::mock(DraftDatasetFileRepository::class);
        $draftRepository->shouldReceive('getBySubmissionId')->andReturn(LazyCollection::make($draftFiles));
        app()->instance(DraftDatasetFileRepository::class, $draftRepository);
        Mockery::mock('overload:' . DraftDatasetFilesValidator::class)
            ->shouldReceive('datasetHasReadmeFile')->andReturn(true);

        $datasetFiles = [$this->createDatasetFile(1, 'README.txt'), $this->createDatasetFile(2, 'Data.csv')];
        $dataset = new Dataset();
        $dataset->setFiles($datasetFiles);
        $dataset->setRelatedPublication(new DatasetRelatedPublication(null, 'Citation', null, null, null));
        Mockery::mock('overload:' . SubmissionDatasetFactory::class)
            ->shouldReceive('getDataset')->andReturn($dataset);

        $collectionActions = $this->getMockBuilder(DataverseCollectionActions::class)
            ->disableOriginalConstructor()->onlyMethods(['getRequiredMetadata', 'getLicenses'])->getMock();
        $collectionActions->method('getRequiredMetadata')->willReturn(['citation' => ['fields' => []]]);
        $collectionActions->method('getLicenses')->willReturn([['name' => 'CC0 1.0']]);
        $client = Mockery::mock('overload:' . DataverseClient::class);
        $client->shouldReceive('getDataverseCollectionActions')->andReturn($collectionActions);

        $service = Mockery::mock('overload:' . DatasetService::class);
        $service->shouldReceive('deposit')->once()
            ->withArgs(fn ($submission, Dataset $deposited) => $deposited->getFiles() === $datasetFiles
                && !$deposited->hasData('submissionId')
                && !$deposited->hasData('persistentId'))
            ->andReturn(['status' => 'Success']);

        $request = Request::create('/datasets', 'POST', [
            'datasetTitle' => 'Title',
            'datasetDescription' => 'Description',
            'datasetKeywords' => [Locale::getLocale() => ['Keyword']],
            'datasetLanguage' => 'English',
            'datasetSubject' => 'Other',
            'datasetLicense' => 'CC0 1.0',
            'datasetRelationType' => 'IsSupplementTo',
            'datasetFiles' => [['path' => '/etc/passwd', 'originalFileName' => 'passwd']],
            'submissionId' => $this->submission->getId(),
            'datasetPersistentId' => 'doi:10.5072/FK2/UNTRUSTED',
        ]);

        $this->assertSame(200, $this->createController()->add($request)->getStatusCode());
    }
}
