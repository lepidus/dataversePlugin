<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.dataStatement.DataStatement');
import('plugins.generic.dataverse.classes.services.DataStatementService');

class DataStatementTest extends PKPTestCase
{
    public function testGettersAndSetters(): void
    {
        $id = 100;
        $type = DATA_STATEMENT_TYPE_IN_MANUSCRIPT;
        $url = 'http://link.to/data';
        $datasetId = 1;
        $reason = 'Has sensitive data';

        $dataStatement = new DataStatement();
        $dataStatement->setId($id);
        $dataStatement->setType($type);
        $dataStatement->setUrl($url);
        $dataStatement->setDatasetId($datasetId);
        $dataStatement->setReason($reason);

        $this->assertEquals($id, $dataStatement->getId());
        $this->assertEquals($type, $dataStatement->getType());
        $this->assertEquals($url, $dataStatement->getUrl());
        $this->assertEquals($datasetId, $dataStatement->getDatasetId());
        $this->assertEquals($reason, $dataStatement->getReason());
    }
}
