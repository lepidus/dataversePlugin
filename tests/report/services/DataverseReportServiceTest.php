<?php

use PKP\tests\DatabaseTestCase;
use APP\core\Application;
use APP\submission\Submission;
use APP\decision\Decision;
use APP\plugins\generic\dataverse\tests\report\traits\ReportTestsHelperTrait;
use APP\plugins\generic\dataverse\classes\dispatchers\DataStatementDispatcher;
use APP\plugins\generic\dataverse\report\services\queryBuilders\DataverseReportQueryBuilder;
use APP\plugins\generic\dataverse\report\services\DataverseReportService;
use APP\plugins\generic\dataverse\report\classes\DataverseStatsReport;
use APP\plugins\generic\dataverse\classes\services\DataStatementService;
use APP\plugins\generic\dataverse\DataversePlugin;
use Illuminate\Support\Facades\DB;

class DataverseReportServiceTest extends DatabaseTestCase
{
    use ReportTestsHelperTrait;

    private $context;

    public function setUp(): void
    {
        parent::setUp();
        $plugin = new DataversePlugin();
        $dispatcher = new DataStatementDispatcher($plugin);
        $this->context = $this->createTestContext();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $contextDAO = Application::getContextDAO();
        $contextDAO->deleteObject($this->context);
    }

    private function getReportService(): DataverseReportService
    {
        return new DataverseReportService($this->context->getId(), DataverseStatsReport::OJS_APP_NAME);
    }

    private function getOpsReportService(): DataverseReportService
    {
        return new DataverseReportService($this->context->getId(), DataverseStatsReport::OPS_APP_NAME);
    }

    private function setPublicationDate(int $submissionId, string $datePublished): void
    {
        DB::table('publications')
            ->where('submission_id', '=', $submissionId)
            ->update(['date_published' => $datePublished]);
    }

    public function testGetQueryBuilder(): void
    {
        $reportService = $this->getReportService();
        $this->assertInstanceOf(
            DataverseReportQueryBuilder::class,
            $reportService->getQueryBuilder()
        );
    }

    public function testCountSubmissions(): void
    {
        $this->createTestSubmission(Submission::STATUS_QUEUED, Decision::ACCEPT);
        $this->createTestSubmission(Submission::STATUS_PUBLISHED, Decision::ACCEPT);

        $reportService = $this->getReportService();
        $this->assertEquals(2, $reportService->getAcceptedSubmissionsCount());
        $this->assertEquals(0, $reportService->getDeclinedSubmissionsCount());

        $this->createTestSubmission(Submission::STATUS_QUEUED, Decision::DECLINE);
        $this->createTestSubmission(Submission::STATUS_DECLINED, Decision::DECLINE);
        $this->createTestSubmission(Submission::STATUS_DECLINED, Decision::INITIAL_DECLINE);
        $this->assertEquals(2, $reportService->getDeclinedSubmissionsCount());
    }

    public function testCountPublishedSubmissions(): void
    {
        $this->createTestSubmission(Submission::STATUS_PUBLISHED, Decision::ACCEPT);
        $this->createTestSubmission(Submission::STATUS_PUBLISHED, Decision::DECLINE);
        $this->createTestSubmission(Submission::STATUS_PUBLISHED, Decision::INITIAL_DECLINE);

        $reportService = $this->getReportService();
        $this->assertEquals(3, $reportService->getPublishedSubmissionsCount());
    }

    public function testCountSubmissionsWithDataset(): void
    {
        $this->createTestSubmission(Submission::STATUS_DECLINED, Decision::DECLINE, true);
        $this->createTestSubmission(Submission::STATUS_QUEUED, Decision::DECLINE, true);
        $this->createTestSubmission(Submission::STATUS_QUEUED, Decision::DECLINE, false);
        $this->createTestSubmission(Submission::STATUS_QUEUED, Decision::ACCEPT, true);
        $this->createTestSubmission(Submission::STATUS_QUEUED, Decision::ACCEPT, false);

        $reportService = $this->getReportService();
        $this->assertEquals(1, $reportService->getAcceptedSubmissionsWithDatasetCount());
        $this->assertEquals(1, $reportService->getDeclinedSubmissionsWithDatasetCount());
    }

    public function testCountPublishedSubmissionsWithDataset(): void
    {
        $this->createTestSubmission(Submission::STATUS_PUBLISHED, Decision::ACCEPT, true);
        $this->createTestSubmission(Submission::STATUS_PUBLISHED, Decision::DECLINE, true);
        $this->createTestSubmission(Submission::STATUS_PUBLISHED, Decision::INITIAL_DECLINE, true);

        $reportService = $this->getReportService();
        $this->assertEquals(3, $reportService->getPublishedSubmissionsWithDatasetCount());
    }

    public function testCountsDeclinedSubmissionsAfterDatasetDeletion(): void
    {
        $declinedSubWithDataset = $this->createTestSubmission(Submission::STATUS_DECLINED, Decision::DECLINE, true);
        $declinedSubWithJustLog = $this->createTestSubmission(Submission::STATUS_DECLINED, Decision::DECLINE, false);
        $this->addEventLogToSubmission(
            $declinedSubWithJustLog->getId(),
            'plugins.generic.dataverse.log.researchDataDeposited'
        );
        $declinedSubWithTranslatedLog = $this->createTestSubmission(Submission::STATUS_DECLINED, Decision::DECLINE, false);
        $this->addEventLogToSubmission(
            $declinedSubWithTranslatedLog->getId(),
            'Research data deposited: doi:10.12345/FKII/1234',
            true
        );
        $declinedSubWithLogAndDataset = $this->createTestSubmission(Submission::STATUS_DECLINED, Decision::DECLINE, true);
        $this->addEventLogToSubmission(
            $declinedSubWithLogAndDataset->getId(),
            'plugins.generic.dataverse.log.researchDataDeposited'
        );

        $reportService = $this->getReportService();
        $this->assertEquals(4, $reportService->getDeclinedSubmissionsWithDatasetCount());
    }

    public function testCountsTotalSubmissions(): void
    {
        $this->createTestSubmission(Submission::STATUS_QUEUED);
        $this->createTestSubmission(Submission::STATUS_QUEUED);
        $this->createTestSubmission(Submission::STATUS_QUEUED, null, true);
        $this->createTestSubmission(Submission::STATUS_DECLINED, Decision::DECLINE);
        $this->createTestSubmission(Submission::STATUS_DECLINED, Decision::INITIAL_DECLINE);
        $this->createTestSubmission(Submission::STATUS_PUBLISHED, Decision::ACCEPT, true);
        $this->createTestSubmission(Submission::STATUS_PUBLISHED, Decision::DECLINE, false);
        $this->createTestSubmission(Submission::STATUS_PUBLISHED, Decision::INITIAL_DECLINE, false);

        $reportService = $this->getReportService();
        $this->assertEquals(8, $reportService->getTotalSubmissionsCount());
    }

    public function testGetsSubmissionsStatementStats(): void
    {
        $firstAcceptedSub = $this->createTestSubmission(Submission::STATUS_QUEUED, Decision::ACCEPT);
        $secondAcceptedSub = $this->createTestSubmission(Submission::STATUS_PUBLISHED, Decision::ACCEPT);

        $firstDeclinedSub = $this->createTestSubmission(Submission::STATUS_DECLINED, Decision::DECLINE);
        $secondDeclinedSub = $this->createTestSubmission(Submission::STATUS_DECLINED, Decision::INITIAL_DECLINE);

        $this->addDataStatementTypesToSubmission($firstAcceptedSub, [
            DataStatementService::DATA_STATEMENT_TYPE_IN_MANUSCRIPT,
            DataStatementService::DATA_STATEMENT_TYPE_REPO_AVAILABLE
        ]);
        $this->addDataStatementTypesToSubmission($secondAcceptedSub, [
            DataStatementService::DATA_STATEMENT_TYPE_REPO_AVAILABLE,
            DataStatementService::DATA_STATEMENT_TYPE_ON_DEMAND
        ]);
        $this->addDataStatementTypesToSubmission($firstDeclinedSub, [
            DataStatementService::DATA_STATEMENT_TYPE_ON_DEMAND,
            DataStatementService::DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE
        ]);
        $this->addDataStatementTypesToSubmission($secondDeclinedSub, [
            DataStatementService::DATA_STATEMENT_TYPE_ON_DEMAND,
            DataStatementService::DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE
        ]);

        $reportService = $this->getReportService();
        $acceptedStatementStats = $reportService->getAcceptedStatementStatistics();
        $declinedStatementStats = $reportService->getDeclinedStatementStatistics();

        $this->assertEquals([1, 2, 1, 0], $acceptedStatementStats->getStats());
        $this->assertEquals([0, 0, 2, 2], $declinedStatementStats->getStats());
    }

    public function testGetsPublishedSubmissionsStatementStats(): void
    {
        $firstPublishedSub = $this->createTestSubmission(Submission::STATUS_PUBLISHED, Decision::ACCEPT);
        $secondPublishedSub = $this->createTestSubmission(Submission::STATUS_PUBLISHED, Decision::ACCEPT);

        $this->addDataStatementTypesToSubmission($firstPublishedSub, [
            DataStatementService::DATA_STATEMENT_TYPE_IN_MANUSCRIPT,
            DataStatementService::DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED
        ]);
        $this->addDataStatementTypesToSubmission($secondPublishedSub, [
            DataStatementService::DATA_STATEMENT_TYPE_REPO_AVAILABLE,
            DataStatementService::DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED
        ]);

        $reportService = $this->getReportService();
        $publishedStatementStats = $reportService->getPublishedStatementStatistics();

        $this->assertEquals([1, 1, 0, 0], $publishedStatementStats->getStats());
    }

    public function testGetsTotalSubmissionsStatementStats(): void
    {
        $firstSub = $this->createTestSubmission(Submission::STATUS_PUBLISHED, Decision::ACCEPT);
        $secondSub = $this->createTestSubmission(Submission::STATUS_QUEUED);
        $thirdSub = $this->createTestSubmission(Submission::STATUS_DECLINED, Decision::DECLINE);

        $this->addDataStatementTypesToSubmission($firstSub, [
            DataStatementService::DATA_STATEMENT_TYPE_IN_MANUSCRIPT
        ]);
        $this->addDataStatementTypesToSubmission($secondSub, [
            DataStatementService::DATA_STATEMENT_TYPE_IN_MANUSCRIPT,
            DataStatementService::DATA_STATEMENT_TYPE_REPO_AVAILABLE
        ]);
        $this->addDataStatementTypesToSubmission($thirdSub, [
            DataStatementService::DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE,
        ]);

        $reportService = $this->getReportService();
        $publishedStatementStats = $reportService->getTotalStatementStatistics();

        $this->assertEquals([2, 1, 0, 1], $publishedStatementStats->getStats());
    }

    public function testConsidersDateSubmittedInterval(): void
    {
        $beforePublishedSub = $this->createTestSubmission(Submission::STATUS_PUBLISHED, null, false, '2026-06-11');
        $beforeDeclinedSub = $this->createTestSubmission(Submission::STATUS_DECLINED, Decision::DECLINE, false, '2026-06-11');
        $withinAcceptedSub = $this->createTestSubmission(Submission::STATUS_QUEUED, Decision::ACCEPT, false, '2026-06-13');
        $withinDeclinedSub = $this->createTestSubmission(Submission::STATUS_DECLINED, Decision::DECLINE, false, '2026-06-13');
        $withinPublishedSub = $this->createTestSubmission(Submission::STATUS_PUBLISHED, null, false, '2026-06-13');
        $afterAcceptedSub = $this->createTestSubmission(Submission::STATUS_QUEUED, Decision::ACCEPT, false, '2026-06-16');

        $startInterval = '2026-06-12';
        $endInterval = '2026-06-15';

        $reportService = $this->getReportService();

        $this->assertEquals(2, $reportService->getAcceptedSubmissionsCount());
        $this->assertEquals(2, $reportService->getDeclinedSubmissionsCount());
        $this->assertEquals(2, $reportService->getPublishedSubmissionsCount());
        $this->assertEquals(6, $reportService->getTotalSubmissionsCount());

        $reportService->setDateSubmittedInterval($startInterval, $endInterval);

        $this->assertEquals(1, $reportService->getAcceptedSubmissionsCount());
        $this->assertEquals(1, $reportService->getDeclinedSubmissionsCount());
        $this->assertEquals(1, $reportService->getPublishedSubmissionsCount());
        $this->assertEquals(3, $reportService->getTotalSubmissionsCount());
    }

    public function testConsidersFinalDecisionDateIntervalInOjs(): void
    {
        $beforeAcceptedSub = $this->createTestSubmission(Submission::STATUS_QUEUED);
        $withinAcceptedSub = $this->createTestSubmission(Submission::STATUS_QUEUED);
        $afterAcceptedSub = $this->createTestSubmission(Submission::STATUS_QUEUED);
        $beforeDeclinedSub = $this->createTestSubmission(Submission::STATUS_DECLINED);
        $withinDeclinedSub = $this->createTestSubmission(Submission::STATUS_DECLINED);

        $this->addDecision(Decision::ACCEPT, $beforeAcceptedSub->getId(), '2026-06-11 10:00:00');
        $this->addDecision(Decision::ACCEPT, $withinAcceptedSub->getId(), '2026-06-13 10:00:00');
        $this->addDecision(Decision::ACCEPT, $afterAcceptedSub->getId(), '2026-06-16 10:00:00');
        $this->addDecision(Decision::DECLINE, $beforeDeclinedSub->getId(), '2026-06-11 10:00:00');
        $this->addDecision(Decision::DECLINE, $withinDeclinedSub->getId(), '2026-06-14 10:00:00');

        $reportService = $this->getReportService();

        $this->assertEquals(3, $reportService->getAcceptedSubmissionsCount());
        $this->assertEquals(2, $reportService->getDeclinedSubmissionsCount());
        $this->assertEquals(5, $reportService->getTotalSubmissionsCount());

        $reportService->setFinalDecisionDateInterval(
            '2026-06-12 00:00:00',
            '2026-06-15 23:59:59'
        );

        $this->assertEquals(1, $reportService->getAcceptedSubmissionsCount());
        $this->assertEquals(1, $reportService->getDeclinedSubmissionsCount());
        $this->assertEquals(2, $reportService->getTotalSubmissionsCount());
    }

    public function testConsidersFinalDecisionDateIntervalInOps(): void
    {
        $beforePublishedSub = $this->createTestSubmission(Submission::STATUS_PUBLISHED);
        $withinPublishedSub = $this->createTestSubmission(Submission::STATUS_PUBLISHED);
        $withinDeclinedSub = $this->createTestSubmission(Submission::STATUS_DECLINED);
        $withinInitialDeclineSub = $this->createTestSubmission(Submission::STATUS_DECLINED);
        $afterDeclinedSub = $this->createTestSubmission(Submission::STATUS_DECLINED);

        $this->setPublicationDate($beforePublishedSub->getId(), '2026-06-11');
        $this->setPublicationDate($withinPublishedSub->getId(), '2026-06-13');

        $this->addDecision(Decision::DECLINE, $withinDeclinedSub->getId(), '2026-06-14 10:00:00');
        $this->addDecision(Decision::INITIAL_DECLINE, $withinInitialDeclineSub->getId(), '2026-06-15 10:00:00');
        $this->addDecision(Decision::DECLINE, $afterDeclinedSub->getId(), '2026-06-16 10:00:00');

        $reportService = $this->getOpsReportService();

        $this->assertEquals(2, $reportService->getPublishedSubmissionsCount());
        $this->assertEquals(3, $reportService->getDeclinedSubmissionsCount());
        $this->assertEquals(5, $reportService->getTotalSubmissionsCount());

        $reportService->setFinalDecisionDateInterval(
            '2026-06-12 00:00:00',
            '2026-06-15 23:59:59'
        );

        $this->assertEquals(1, $reportService->getPublishedSubmissionsCount());
        $this->assertEquals(2, $reportService->getDeclinedSubmissionsCount());
        $this->assertEquals(3, $reportService->getTotalSubmissionsCount());
    }
}
