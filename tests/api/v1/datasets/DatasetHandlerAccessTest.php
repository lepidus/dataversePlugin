<?php

use APP\core\Application;
use APP\core\Request as PKPRequest;
use APP\plugins\generic\dataverse\api\v1\datasets\DatasetHandler;
use APP\plugins\generic\dataverse\classes\dataverseStudy\DataverseStudy;
use APP\plugins\generic\dataverse\classes\dataverseStudy\Repository as DataverseStudyRepository;
use APP\plugins\generic\dataverse\classes\entities\DatasetFile;
use APP\plugins\generic\dataverse\classes\services\DatasetFileService;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use APP\submission\Submission;
use PKP\core\APIResponse;
use PKP\core\Dispatcher;
use PKP\core\Registry;
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
        return [DataverseStudyRepository::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        ini_set('error_log', '/dev/null');

        $this->submission = new Submission();
        $this->submission->setId(23);

        $this->study = new DataverseStudy();
        $this->study->setId(self::STUDY_ID);
        $this->study->setSubmissionId($this->submission->getId());
        $this->study->setPersistentId('doi:10.5072/FK2/TEST');

        $studyRepository = Mockery::mock(DataverseStudyRepository::class);
        $studyRepository->shouldReceive('get')->with(self::STUDY_ID)->andReturn($this->study);
        app()->instance(DataverseStudyRepository::class, $studyRepository);

        $this->mockContextRequest();
    }

    private function mockContextRequest(): void
    {
        $context = Application::getContextDAO()->newDataObject();
        $context->setId(1);
        $context->setData('path', 'test');
        $context->setData('supportedSubmissionLocales', ['en']);
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

    private function createHandler(): DatasetHandler
    {
        $handler = $this->getMockBuilder(DatasetHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAuthorizedContextObject'])
            ->getMock();
        $handler->method('getAuthorizedContextObject')
            ->with(Application::ASSOC_TYPE_SUBMISSION)->willReturn($this->submission);

        return $handler;
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

}
