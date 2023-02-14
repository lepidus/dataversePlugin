<?php

import('plugins.generic.dataverse.tests.dataverseAPI.endpoints.DataverseEndpointsTestCase');
import('plugins.generic.dataverse.classes.dataverseAPI.endpoints.NativeAPIEndpoints');

class NativeAPIEndpointsTest extends DataverseEndpointsTestCase
{
    protected function createDataverseEndpoints(DataverseServer $server): DataverseEndpoints
    {
        return new NativeAPIEndpoints($server);
    }

    public function testReturnsCorrectDataverseServerUrl(): void
    {
        $expectedServerUrl = 'https://demo.dataverse.org/api/dataverses/:root';
        $ServerUrl = $this->endpoints->getDataverseServerUrl();

        $this->assertEquals($expectedServerUrl, $ServerUrl);
    }

    public function testReturnsCorrectDataverseCollectionUrl(): void
    {
        $expectedCollectionUrl = 'https://demo.dataverse.org/api/dataverses/example';
        $collectionUrl = $this->endpoints->getDataverseCollectionUrl();

        $this->assertEquals($expectedCollectionUrl, $collectionUrl);
    }

    public function testReturnsCorrectDatasetUrl(): void
    {
        $persistentId = 'doi:10.1234/AB5/CD6EF7';
        $expectedDatasetUrl = 'https://demo.dataverse.org/api/datasets/export?exporter=dataverse_json&persistentId=' . $persistentId;
        $datasetUrl = $this->endpoints->getDatasetDataUrl($persistentId);

        $this->assertEquals($expectedDatasetUrl, $datasetUrl);
    }
}
