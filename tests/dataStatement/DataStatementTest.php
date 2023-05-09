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
        $links = ['http://link.to/data'];
        $datasetId = 1;
        $reason = 'Has sensitive data';
        $locale = 'en_US';

        $dataStatement = new DataStatement();
        $dataStatement->setId($id);
        $dataStatement->setType($type);
        $dataStatement->setLinks($links);
        $dataStatement->setDatasetId($datasetId);
        $dataStatement->setReason($reason, $locale);

        $this->assertEquals($id, $dataStatement->getId());
        $this->assertEquals($type, $dataStatement->getType());
        $this->assertEquals($links, $dataStatement->getLinks());
        $this->assertEquals($datasetId, $dataStatement->getDatasetId());
        $this->assertEquals($reason, $dataStatement->getReason($locale));
    }
}
