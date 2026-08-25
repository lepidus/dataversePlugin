<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.services.DataStatementService');
import('plugins.generic.dataverse.report.classes.DataStatementStats');

class DataStatementStatsTest extends PKPTestCase
{
    private int $inManuscriptCount = 3;
    private int $repoAvailableCount = 5;
    private int $onDemandCount = 17;
    private int $publiclyUnavailableCount = 10;

    public function testStatementClassReturnsAllCounts(): void
    {
        $stats = [
            DATA_STATEMENT_TYPE_IN_MANUSCRIPT => $this->inManuscriptCount,
            DATA_STATEMENT_TYPE_REPO_AVAILABLE => $this->repoAvailableCount,
            DATA_STATEMENT_TYPE_ON_DEMAND => $this->onDemandCount,
            DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE => $this->publiclyUnavailableCount
        ];
        $statementStats = new DataStatementStats($stats);

        $this->assertEquals(array_values($stats), $statementStats->getStats());
    }
}
