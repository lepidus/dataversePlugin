<?php

use PKP\tests\PKPTestCase;
use APP\plugins\generic\dataverse\classes\services\DataStatementService;
use APP\plugins\generic\dataverse\report\classes\DataStatementStats;

class DataStatementStatsTest extends PKPTestCase
{
    private int $inManuscriptCount = 3;
    private int $repoAvailableCount = 5;
    private int $dataverseSubmittedCount = 2;
    private int $onDemandCount = 17;
    private int $publiclyUnavailableCount = 10;

    public function testStatementClassReturnsAllCounts(): void
    {
        $stats = [
            DataStatementService::DATA_STATEMENT_TYPE_IN_MANUSCRIPT => $this->inManuscriptCount,
            DataStatementService::DATA_STATEMENT_TYPE_REPO_AVAILABLE => $this->repoAvailableCount,
            DataStatementService::DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED => $this->dataverseSubmittedCount,
            DataStatementService::DATA_STATEMENT_TYPE_ON_DEMAND => $this->onDemandCount,
            DataStatementService::DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE => $this->publiclyUnavailableCount
        ];
        $statementStats = new DataStatementStats($stats);

        $this->assertEquals(array_values($stats), $statementStats->getStats());
    }
}
