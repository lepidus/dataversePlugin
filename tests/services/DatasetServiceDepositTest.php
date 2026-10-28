<?php

use APP\plugins\generic\dataverse\classes\entities\Dataset;
use APP\plugins\generic\dataverse\classes\entities\DatasetFile;
use APP\plugins\generic\dataverse\classes\entities\DatasetIdentifier;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\classes\services\DatasetService;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use APP\submission\Submission;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PKP\tests\PKPTestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class DatasetServiceDepositTest extends PKPTestCase
{
    public function testFailedCleanupAfterFileDepositErrorIsReportedAsDepositError(): void
    {
        ini_set('error_log', '/dev/null');
        $identifier = new DatasetIdentifier();
        $identifier->setPersistentId('doi:10.5072/FK2/TEST');
        $datasetActions = Mockery::mock();
        $datasetActions->shouldReceive('create')->andReturn($identifier);
        $datasetActions->shouldReceive('delete')->andThrow(new DataverseException('Dataset is locked', 403));
        $fileActions = Mockery::mock();
        $fileActions->shouldReceive('add')->andThrow(new DataverseException('File rejected', 400));
        $fileActions->shouldReceive('waitForIngest');
        $client = Mockery::mock('overload:' . DataverseClient::class);
        $client->shouldReceive('getDatasetActions')->andReturn($datasetActions);
        $client->shouldReceive('getDatasetFileActions')->andReturn($fileActions);

        $datasetFile = new DatasetFile();
        $datasetFile->setOriginalFileName('Data.csv');
        $datasetFile->setPath('/tmp/Data.csv');
        $dataset = new Dataset();
        $dataset->setFiles([$datasetFile]);

        $service = $this->getMockBuilder(DatasetService::class)
            ->onlyMethods(['registerAndNotifyError'])
            ->getMock();

        $depositInfo = $service->deposit(new Submission(), $dataset);

        $this->assertSame('Error', $depositInfo['status']);
        $this->assertSame('plugins.generic.dataverse.error.datasetFileDeposit', $depositInfo['message']);
    }

    public function testIncompleteDatasetIsDeletedOnlyAfterTheIngestFinishes(): void
    {
        ini_set('error_log', '/dev/null');
        $identifier = new DatasetIdentifier();
        $identifier->setPersistentId('doi:10.5072/FK2/TEST');
        $datasetActions = Mockery::mock();
        $datasetActions->shouldReceive('create')->andReturn($identifier);
        $fileActions = Mockery::mock();
        $fileActions->shouldReceive('add')->andThrow(new DataverseException('Ingest still running'));
        $fileActions->shouldReceive('waitForIngest')->with('doi:10.5072/FK2/TEST')->once()->globally()->ordered();
        $datasetActions->shouldReceive('delete')->with('doi:10.5072/FK2/TEST')->once()->globally()->ordered();
        $client = Mockery::mock('overload:' . DataverseClient::class);
        $client->shouldReceive('getDatasetActions')->andReturn($datasetActions);
        $client->shouldReceive('getDatasetFileActions')->andReturn($fileActions);

        $datasetFile = new DatasetFile();
        $datasetFile->setOriginalFileName('Data.csv');
        $datasetFile->setPath('/tmp/Data.csv');
        $dataset = new Dataset();
        $dataset->setFiles([$datasetFile]);

        $service = $this->getMockBuilder(DatasetService::class)
            ->onlyMethods(['registerAndNotifyError'])
            ->getMock();

        $this->assertSame('Error', $service->deposit(new Submission(), $dataset)['status']);
        Mockery::close();
    }
}
