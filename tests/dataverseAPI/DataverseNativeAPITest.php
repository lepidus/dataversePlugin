<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.dataverseAPI.DataverseNativeAPI');

class DataverseNativeAPITest extends PKPTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testReturnsACollectionOperationsClass(): void
    {
        $dataverseNativeAPI = new DataverseNativeAPI();
        $collectionOperations = $dataverseNativeAPI->getCollectionOperations();

        $this->assertInstanceOf(CollectionOperationsInterface::class, $collectionOperations);
    }

    public function testReturnsADatasetOperationsClass(): void
    {
        $dataverseNativeAPI = new DataverseNativeAPI();
        $datasetOperations = $dataverseNativeAPI->getDatasetOperations();

        $this->assertInstanceOf(DatasetOperationsInterface::class, $datasetOperations);
    }

    public function testDataverseOperationsIsConfigured(): void
    {
        $config = new DataverseCredentials();
        $config->setDataverseUrl('https://serverUrl/dataverse/dataverseAlias');
        $config->setAPIToken('apiKey');

        $dataverseNativeAPI = new DataverseNativeAPI();
        $dataverseNativeAPI->configure($config);

        $collectionOperations = $dataverseNativeAPI->getCollectionOperations();

        $this->assertEquals('https://serverUrl/api/', $collectionOperations->getBaseAPIURL());
    }
}
