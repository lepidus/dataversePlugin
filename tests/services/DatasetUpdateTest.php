<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.services.DatasetService');
import('plugins.generic.dataverse.classes.dataverseStudy.DataverseStudyDAO');
import('plugins.generic.dataverse.classes.entities.Dataset');
import('plugins.generic.dataverse.dataverseAPI.DataverseClient');
import('plugins.generic.dataverse.dataverseAPI.actions.DatasetActions');

class DatasetUpdateTest extends PKPTestCase
{
    protected function tearDown(): void
    {
        DAORegistry::registerDAO('DataverseStudyDAO', new DataverseStudyDAO());
        parent::tearDown();
    }

    public function testDatasetNoLongerAssociatedWithASubmissionIsNotUpdated(): void
    {
        $dataset = new Dataset();
        $dataset->setPersistentId('doi:10.5072/FK2/UNLINKED');
        $dataset->setVersionState('DRAFT');

        $datasetActions = $this->createMock(DatasetActions::class);
        $datasetActions->method('get')->willReturn($dataset);
        $datasetActions->expects($this->never())->method('update');
        $client = $this->createMock(DataverseClient::class);
        $client->method('getDatasetActions')->willReturn($datasetActions);

        $studyDao = $this->createMock(DataverseStudyDAO::class);
        $studyDao->method('getByPersistentId')->willReturn(null);
        DAORegistry::registerDAO('DataverseStudyDAO', $studyDao);

        $service = $this->getMockBuilder(DatasetService::class)->onlyMethods(['getDataverseClient'])->getMock();
        $service->method('getDataverseClient')->willReturn($client);

        $service->update(['persistentId' => 'doi:10.5072/FK2/UNLINKED', 'title' => 'New title']);
    }
}
