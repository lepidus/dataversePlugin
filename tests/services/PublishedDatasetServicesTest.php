<?php

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.file.TemporaryFile');
import('plugins.generic.dataverse.classes.services.DatasetService');
import('plugins.generic.dataverse.classes.services.DatasetFileService');
import('plugins.generic.dataverse.classes.dataverseStudy.DataverseStudy');
import('plugins.generic.dataverse.classes.exception.PublishedDatasetException');
import('plugins.generic.dataverse.classes.exception.DataverseException');
import('plugins.generic.dataverse.classes.entities.Dataset');
import('plugins.generic.dataverse.dataverseAPI.actions.DatasetActions');
import('plugins.generic.dataverse.dataverseAPI.actions.DatasetFileActions');

class PublishedDatasetServicesTest extends PKPTestCase
{
    private $datasetActions;
    private $fileActions;
    private $client;
    private $study;

    protected function setUp(): void
    {
        parent::setUp();

        $dataset = new Dataset();
        $dataset->setVersionState(Dataset::VERSION_STATE_RELEASED);

        $this->datasetActions = $this->createMock(DatasetActions::class);
        $this->datasetActions->method('get')->willReturn($dataset);
        $this->fileActions = $this->createMock(DatasetFileActions::class);
        $this->client = $this->createMock(DataverseClient::class);
        $this->client->method('getDatasetActions')->willReturn($this->datasetActions);
        $this->client->method('getDatasetFileActions')->willReturn($this->fileActions);

        $this->study = new DataverseStudy();
        $this->study->setSubmissionId(0);
        $this->study->setPersistentId('doi:10.5072/FK2/TEST');
    }

    private function createService(string $serviceClass)
    {
        $service = $this->getMockBuilder($serviceClass)->onlyMethods(['getDataverseClient'])->getMock();
        $service->method('getDataverseClient')->willReturn($this->client);

        return $service;
    }

    public function testUpdateRefusesPublishedDataset(): void
    {
        $this->datasetActions->expects($this->never())->method('update');
        $this->expectException(PublishedDatasetException::class);

        $this->createService(DatasetService::class)->update(['persistentId' => $this->study->getPersistentId()]);
    }

    public function testDeleteRefusesPublishedDataset(): void
    {
        $this->datasetActions->expects($this->never())->method('delete');
        $this->expectException(PublishedDatasetException::class);

        $this->createService(DatasetService::class)->delete($this->study, null);
    }

    public function testFileCannotBeAddedToPublishedDataset(): void
    {
        $this->fileActions->expects($this->never())->method('add');
        $this->expectException(PublishedDatasetException::class);

        $this->createService(DatasetFileService::class)->add($this->study, new TemporaryFile());
    }

    public function testFileCannotBeDeletedFromPublishedDataset(): void
    {
        $this->fileActions->expects($this->never())->method('delete');
        $this->expectException(PublishedDatasetException::class);

        $this->createService(DatasetFileService::class)->delete($this->study, '41', 'data.csv');
    }

    public function testUpdateKeepsIgnoringDataverseFailures(): void
    {
        $datasetActions = $this->createMock(DatasetActions::class);
        $datasetActions->method('get')->willThrowException(new DataverseException('Not found', 404));
        $client = $this->createMock(DataverseClient::class);
        $client->method('getDatasetActions')->willReturn($datasetActions);
        $service = $this->getMockBuilder(DatasetService::class)->onlyMethods(['getDataverseClient'])->getMock();
        $service->method('getDataverseClient')->willReturn($client);

        $service->update(['persistentId' => $this->study->getPersistentId()]);

        $this->assertTrue(true);
    }
}
