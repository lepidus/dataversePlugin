<?php

use APP\plugins\generic\dataverse\classes\dataverseStudy\DataverseStudy;
use APP\plugins\generic\dataverse\classes\entities\Dataset;
use APP\plugins\generic\dataverse\classes\entities\DatasetFile;
use APP\plugins\generic\dataverse\controllers\grid\DatasetReviewGridHandler;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use APP\submission\Submission;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PKP\tests\PKPTestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class DatasetReviewGridHandlerTest extends PKPTestCase
{
    private function createDatasetFile(int $id): DatasetFile
    {
        $datasetFile = new DatasetFile();
        $datasetFile->setId($id);
        $datasetFile->setFileName('Data' . $id . '.csv');

        return $datasetFile;
    }

    private function loadRows(?array $selectedDataFilesForReview): array
    {
        $dataset = new Dataset();
        $dataset->setFiles([$this->createDatasetFile(41), $this->createDatasetFile(42)]);
        $datasetActions = Mockery::mock();
        $datasetActions->shouldReceive('get')->andReturn($dataset);
        $client = Mockery::mock('overload:' . DataverseClient::class);
        $client->shouldReceive('getDatasetActions')->andReturn($datasetActions);

        $submission = new Submission();
        $submission->setData('selectedDataFilesForReview', $selectedDataFilesForReview);
        $study = new DataverseStudy();
        $study->setPersistentId('doi:10.5072/FK2/TEST');

        $handler = $this->getMockBuilder(DatasetReviewGridHandler::class)
            ->onlyMethods(['getSubmission'])
            ->getMock();
        $handler->method('getSubmission')->willReturn($submission);
        (new ReflectionProperty(DatasetReviewGridHandler::class, 'study'))->setValue($handler, $study);

        return (new ReflectionMethod($handler, 'loadData'))->invoke($handler, null, null);
    }

    public function testOnlyTheFilesSelectedForReviewAreListed(): void
    {
        $this->assertSame([42], array_keys($this->loadRows([42])));
    }

    public function testNoFileIsListedWhenNoneWasEverSelected(): void
    {
        $this->assertSame([], $this->loadRows(null));
    }
}
