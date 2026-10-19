<?php

use APP\core\Application;
use APP\plugins\generic\dataverse\api\v1\datasets\DatasetController;
use APP\plugins\generic\dataverse\classes\dataverseStudy\DataverseStudy;
use APP\plugins\generic\dataverse\classes\dataverseStudy\Repository as DataverseStudyRepository;
use APP\plugins\generic\dataverse\classes\entities\DatasetFile;
use APP\plugins\generic\dataverse\classes\services\DatasetFileService;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use APP\submission\Submission;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
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

    protected function getMockedContainerKeys(): array
    {
        return [DataverseStudyRepository::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->submission = new Submission();
        $this->submission->setId(23);

        $this->study = new DataverseStudy();
        $this->study->setId(self::STUDY_ID);
        $this->study->setSubmissionId($this->submission->getId());
        $this->study->setPersistentId('doi:10.5072/FK2/TEST');

        $studyRepository = Mockery::mock(DataverseStudyRepository::class);
        $studyRepository->shouldReceive('get')->with(self::STUDY_ID)->andReturn($this->study);
        app()->instance(DataverseStudyRepository::class, $studyRepository);
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

    private function createController(): DatasetController
    {
        $controller = $this->getMockBuilder(DatasetController::class)
            ->onlyMethods(['getAuthorizedContextObject'])
            ->getMock();
        $controller->method('getAuthorizedContextObject')
            ->with(Application::ASSOC_TYPE_SUBMISSION)->willReturn($this->submission);

        return $controller;
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
}
