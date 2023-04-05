<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.entities.DataverseResponse');

class DataverseResponseTest extends PKPTestCase
{
    public function testGetters(): void
    {
        $response = new DataverseResponse(200, 'OK', '{"foo": "bar"}');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getMessage());
        $this->assertEquals('{"foo": "bar"}', $response->getBody());
    }
}
