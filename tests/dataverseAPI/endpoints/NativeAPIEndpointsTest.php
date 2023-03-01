<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.dataverseAPI.endpoints.NativeAPIEndpoints');

class NativeAPIEndpointsTest extends PKPTestCase
{
    private $endpoints;

    protected function setUp(): void
    {
        $this->endpoints = new NativeAPIEndpoints(
            'https://demo.dataverse.org',
            'example'
        );

        parent::setUp();
    }

    public function testReturnsCorrectDataverseServerEndpoint(): void
    {
        $expectedServerEndpoint = 'https://demo.dataverse.org/api/dataverses/:root';
        $ServerEndpoint = $this->endpoints->getDataverseServerEndpoint();

        $this->assertEquals($expectedServerEndpoint, $ServerEndpoint);
    }

    public function testReturnsCorrectDataverseCollectionEndpoint(): void
    {
        $expectedCollectionEndpoint = 'https://demo.dataverse.org/api/dataverses/example';
        $collectionEndpoint = $this->endpoints->getDataverseCollectionEndpoint();

        $this->assertEquals($expectedCollectionEndpoint, $collectionEndpoint);
    }

    public function testReturnsCorrectDatasetEndpoint(): void
    {
        $persistentId = 'doi:10.1234/AB5/CD6EF7';
        $expectedDatasetEndpoint = 'https://demo.dataverse.org/api/datasets/:persistentId/versions/:latest/metadata/citation?persistentId=' . $persistentId;
        $datasetEndpoint = $this->endpoints->getDatasetDataEndpoint($persistentId);

        $this->assertEquals($expectedDatasetEndpoint, $datasetEndpoint);
    }

    public function testReturnsCorrectDatasetFilesEndpoint(): void
    {
        $persistentId = 'doi:10.1234/AB5/CD6EF7';
        $expectedDatasetFilesEndpoint = 'https://demo.dataverse.org/api/datasets/:persistentId/versions/:latest/files?persistentId=' . $persistentId;
        $datasetFilesEndpoint = $this->endpoints->getDatasetFilesEndpoint($persistentId);

        $this->assertEquals($expectedDatasetFilesEndpoint, $datasetFilesEndpoint);
    }
}
