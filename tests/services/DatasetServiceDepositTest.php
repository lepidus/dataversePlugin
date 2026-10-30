<?php

use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfigurationDAO;
use APP\plugins\generic\dataverse\classes\entities\Dataset;
use APP\plugins\generic\dataverse\classes\entities\DatasetFile;
use APP\plugins\generic\dataverse\classes\entities\DatasetIdentifier;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\classes\exception\UploadNotSentException;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\classes\services\DatasetService;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use APP\plugins\generic\dataverse\tests\helpers\CreatesTestContext;
use APP\publication\Publication;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PKP\db\DAORegistry;
use PKP\tests\DatabaseTestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class DatasetServiceDepositTest extends DatabaseTestCase
{
    use CreatesTestContext;

    private $context;
    private Submission $submission;
    private string $filePath;

    protected function setUp(): void
    {
        parent::setUp();
        ini_set('error_log', '/dev/null');
        DAORegistry::registerDAO('DataverseConfigurationDAO', new DataverseConfigurationDAO());
        $this->context = $this->createTestContext();
        $configuration = new DataverseConfiguration();
        $configuration->setDataverseUrl('https://dataverse.invalid/dataverse/placeholder');
        $configuration->setAPIToken('test-token');
        (new DataverseConfigurationDAO())->insert($this->context->getId(), $configuration);
        $submission = new Submission();
        $submission->setData('contextId', $this->context->getId());
        $submissionId = Repo::submission()->add($submission, new Publication(), $this->context);
        $this->submission = Repo::submission()->get($submissionId);
        $this->filePath = tempnam(sys_get_temp_dir(), 'dataverse-deposit-');
        file_put_contents($this->filePath, 'year,value');
    }

    protected function tearDown(): void
    {
        unlink($this->filePath);
        $this->deleteTestContext($this->context);
        parent::tearDown();
    }

    public function testFailedFileUploadKeepsTheDatasetAndBlocksARepeatedDeposit(): void
    {
        $identifier = new DatasetIdentifier();
        $identifier->setPersistentId('doi:10.5072/FK2/TEST');
        $datasetActions = Mockery::mock();
        $datasetActions->shouldReceive('create')->once()->andReturn($identifier);
        $datasetActions->shouldNotReceive('delete');
        $fileActions = Mockery::mock();
        $fileActions->shouldReceive('add')->once()->andThrow(new DataverseException('File rejected', 400));
        $client = Mockery::mock('overload:' . DataverseClient::class);
        $client->shouldReceive('getDatasetActions')->andReturn($datasetActions);
        $client->shouldReceive('getDatasetFileActions')->andReturn($fileActions);
        $service = $this->getMockBuilder(DatasetService::class)
            ->onlyMethods(['registerAndNotifyError'])
            ->getMock();

        $firstAttempt = $service->deposit($this->submission, $this->createDataset());
        $secondAttempt = $service->deposit($this->submission, $this->createDataset());

        $this->assertSame('plugins.generic.dataverse.error.depositPending', $firstAttempt['message']);
        $this->assertSame('plugins.generic.dataverse.error.depositPending', $secondAttempt['message']);
        $operation = DB::table('dataverse_deposits')->where('submission_id', $this->submission->getId())->first();
        $this->assertSame('uploading', $operation->state);
        $this->assertSame('doi:10.5072/FK2/TEST', $operation->persistent_id);
        $this->assertNull(Repo::dataverseStudy()->getBySubmissionId($this->submission->getId()));
    }

    public function testUploadNotSentDuringTheIngestCanBeDepositedAgain(): void
    {
        $identifier = new DatasetIdentifier();
        $identifier->setPersistentId('doi:10.5072/FK2/TEST');
        $datasetActions = Mockery::mock();
        $datasetActions->shouldReceive('create')->once()->andReturn($identifier);
        $datasetActions->shouldReceive('update')->once();
        $fileActions = Mockery::mock();
        $fileActions->shouldReceive('add')->twice()->andReturnUsing(
            fn () => throw new UploadNotSentException('Data.csv', new DataverseException('Ingest still running')),
            fn () => [['id' => 10]]
        );
        $client = Mockery::mock('overload:' . DataverseClient::class);
        $client->shouldReceive('getDatasetActions')->andReturn($datasetActions);
        $client->shouldReceive('getDatasetFileActions')->andReturn($fileActions);
        $service = $this->getMockBuilder(DatasetService::class)
            ->onlyMethods(['registerAndNotifyError', 'registerEventLog'])
            ->getMock();

        $firstAttempt = $service->deposit($this->submission, $this->createDataset());
        $stateAfterFirstAttempt = DB::table('dataverse_deposits')
            ->where('submission_id', $this->submission->getId())
            ->value('state');
        $secondAttempt = $service->deposit($this->submission, $this->createDataset());

        $this->assertSame('plugins.generic.dataverse.error.datasetFileDeposit', $firstAttempt['message']);
        $this->assertSame(
            ['error' => 'Ingest still running', 'fileName' => 'Data.csv'],
            $firstAttempt['messageParams']
        );
        $this->assertSame('ready', $stateAfterFirstAttempt);
        $this->assertSame('Success', $secondAttempt['status']);
        $this->assertSame(
            'doi:10.5072/FK2/TEST',
            Repo::dataverseStudy()->getBySubmissionId($this->submission->getId())->getPersistentId()
        );
    }

    public function testDatasetRejectedByDataverseReportsItsErrorAndCanBeDepositedAgain(): void
    {
        $identifier = new DatasetIdentifier();
        $identifier->setPersistentId('doi:10.5072/FK2/TEST');
        $request = new Request('POST', 'https://dataverse.invalid/api/dataverses/placeholder/datasets');
        $rejection = DataverseException::fromTransferException(
            new ClientException('rejected', $request, new Response(400, [], '{"message":"Invalid license"}'))
        );
        $datasetActions = Mockery::mock();
        $datasetActions->shouldReceive('create')->twice()->andReturnUsing(
            fn () => throw $rejection,
            fn () => $identifier
        );
        $fileActions = Mockery::mock();
        $fileActions->shouldReceive('add')->once()->andReturn([['id' => 10]]);
        $client = Mockery::mock('overload:' . DataverseClient::class);
        $client->shouldReceive('getDatasetActions')->andReturn($datasetActions);
        $client->shouldReceive('getDatasetFileActions')->andReturn($fileActions);
        $service = $this->getMockBuilder(DatasetService::class)
            ->onlyMethods(['registerAndNotifyError', 'registerEventLog'])
            ->getMock();

        $firstAttempt = $service->deposit($this->submission, $this->createDataset());
        $secondAttempt = $service->deposit($this->submission, $this->createDataset());

        $this->assertSame('plugins.generic.dataverse.error.datasetDeposit', $firstAttempt['message']);
        $this->assertSame(['error' => 'Invalid license'], $firstAttempt['messageParams']);
        $this->assertSame('Success', $secondAttempt['status']);
    }

    public function testResumedDepositSendsTheEditedMetadata(): void
    {
        $identifier = new DatasetIdentifier();
        $identifier->setPersistentId('doi:10.5072/FK2/TEST');
        $updatedTitles = [];
        $datasetActions = Mockery::mock();
        $datasetActions->shouldReceive('create')->once()->andReturn($identifier);
        $datasetActions->shouldReceive('update')->once()->andReturnUsing(
            function (Dataset $dataset) use (&$updatedTitles): void {
                $updatedTitles[$dataset->getPersistentId()] = $dataset->getTitle();
            }
        );
        $fileActions = Mockery::mock();
        $fileActions->shouldReceive('add')->twice()->andReturnUsing(
            fn () => throw new UploadNotSentException('Data.csv', new DataverseException('Ingest still running')),
            fn () => [['id' => 10]]
        );
        $client = Mockery::mock('overload:' . DataverseClient::class);
        $client->shouldReceive('getDatasetActions')->andReturn($datasetActions);
        $client->shouldReceive('getDatasetFileActions')->andReturn($fileActions);
        $service = $this->getMockBuilder(DatasetService::class)
            ->onlyMethods(['registerAndNotifyError', 'registerEventLog'])
            ->getMock();

        $service->deposit($this->submission, $this->createDataset());
        $editedDataset = $this->createDataset();
        $editedDataset->setTitle('Edited title');
        $resumedAttempt = $service->deposit($this->submission, $editedDataset);

        $this->assertSame('Success', $resumedAttempt['status']);
        $this->assertSame(['doi:10.5072/FK2/TEST' => 'Edited title'], $updatedTitles);
    }

    public function testDatasetMissingFromDataverseCanBeReplaced(): void
    {
        $identifiers = array_map(function (string $persistentId): DatasetIdentifier {
            $identifier = new DatasetIdentifier();
            $identifier->setPersistentId($persistentId);
            return $identifier;
        }, ['doi:10.5072/FK2/GONE', 'doi:10.5072/FK2/NEW']);
        $datasetActions = Mockery::mock();
        $datasetActions->shouldReceive('create')->twice()->andReturnValues($identifiers);
        $fileActions = Mockery::mock();
        $fileActions->shouldReceive('add')->twice()->andReturn([['id' => 10]]);
        $client = Mockery::mock('overload:' . DataverseClient::class);
        $client->shouldReceive('getDatasetActions')->andReturn($datasetActions);
        $client->shouldReceive('getDatasetFileActions')->andReturn($fileActions);
        $service = $this->getMockBuilder(DatasetService::class)
            ->onlyMethods(['registerAndNotifyError', 'registerEventLog'])
            ->getMock();
        $service->deposit($this->submission, $this->createDataset());

        $service->removeMissingDataset(Repo::dataverseStudy()->getBySubmissionId($this->submission->getId()));
        $newDeposit = $service->deposit($this->submission, $this->createDataset());

        $this->assertSame('Success', $newDeposit['status']);
        $this->assertSame(
            'doi:10.5072/FK2/NEW',
            Repo::dataverseStudy()->getBySubmissionId($this->submission->getId())->getPersistentId()
        );
    }

    private function createDataset(): Dataset
    {
        $datasetFile = new DatasetFile();
        $datasetFile->setId(1);
        $datasetFile->setOriginalFileName('Data.csv');
        $datasetFile->setPath($this->filePath);
        $dataset = new Dataset();
        $dataset->setFiles([$datasetFile]);

        return $dataset;
    }
}
