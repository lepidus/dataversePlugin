<?php

use PKP\tests\PKPTestCase;
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
            'inManuscriptCount' => $this->inManuscriptCount,
            'repoAvailableCount' => $this->repoAvailableCount,
            'dataverseSubmittedCount' => $this->dataverseSubmittedCount,
            'onDemandCount' => $this->onDemandCount,
            'publiclyUnavailableCount' => $this->publiclyUnavailableCount
        ];
        $statementStats = new DataStatementStats($stats);

        $this->assertEquals(array_values($stats), $statementStats->getStats());
    }
}
