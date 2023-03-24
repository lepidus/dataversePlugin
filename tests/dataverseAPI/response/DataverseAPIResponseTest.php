<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.dataverseAPI.response.DataverseAPIResponse');
import('plugins.generic.dataverse.classes.dataverseAPI.entities.DatasetIdentifier');

class DataverseAPIResponseTest extends PKPTestCase
{
    public function testGetters()
    {
        $response = new DataverseAPIResponse(200, 'OK', '{"data": {"id": 1, "name": "John"}}');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getMessage());
        $this->assertEquals('{"data": {"id": 1, "name": "John"}}', $response->getBody());
    }

    public function testGetBodyAsArray()
    {
        $response = new DataverseAPIResponse(200, 'OK', '{"data": {"id": 1, "name": "John"}}');

        $this->assertEquals(['data' => ['id' => 1, 'name' => 'John']], $response->getBodyAsArray());
    }

    public function testGetBodyAsArrayWithEmptyBody()
    {
        $response = new DataverseAPIResponse(200, 'OK');

        $this->assertEquals(null, $response->getBodyAsArray());
    }

    public function testGetBodyAsEntity()
    {
        $response = new DataverseAPIResponse(200, 'OK', '{"data": {"id": 1, "persistentId": "doi:10.5072/FK2/123"}}');

        $datasetIdentifier = $response->getBodyAsEntity(DatasetIdentifier::class);

        $this->assertInstanceOf(DatasetIdentifier::class, $datasetIdentifier);
        $this->assertEquals(1, $datasetIdentifier->getId());
        $this->assertEquals('doi:10.5072/FK2/123', $datasetIdentifier->getPersistentId());
    }

    public function testGetBodyAsEntityWithInvalidDataverseEntityClass()
    {
        $response = new DataverseAPIResponse(200, 'OK', '{"data": {"id": 1, "persistentId": "doi:10.5072/FK2/123"}}');

        $this->expectException(InvalidArgumentException::class);

        $response->getBodyAsEntity('stdClass');
    }

    public function testGetBodyAsEntityWithInvalidData()
    {
        $response = new DataverseAPIResponse(200, 'OK', '{"data": {"id": 1, "name": "John"}}');

        $this->expectException(InvalidArgumentException::class);

        $response->getBodyAsEntity(DatasetIdentifier::class);
    }
}
