<?php

use Illuminate\Database\Capsule\Manager as Capsule;

import('lib.pkp.tests.DatabaseTestCase');
import('plugins.generic.dataverse.report.services.DataverseReportService');
import('plugins.generic.dataverse.tests.report.traits.ReportTestsHelperTrait');
import('plugins.generic.dataverse.classes.dispatchers.DataStatementDispatcher');
import('plugins.generic.dataverse.DataversePlugin');

class DataverseReportServiceTest extends DatabaseTestCase
{
    use ReportTestsHelperTrait;

    private $context;

    public function setUp(): void
    {
        parent::setUp();

        $plugin = new DataversePlugin();
        $dispatcher = new DataStatementDispatcher($plugin);
        HookRegistry::register(
            'Schema::get::publication',
            [$dispatcher, 'addDataStatementToPublicationSchema']
        );
        $this->context = $this->createTestContext();
    }

    protected function getAffectedTables(): array
    {
        return [
            'publications', 'publication_settings',
            'submissions', 'submission_settings',
            'journals', 'journal_settings',
            'edit_decisions',
            'dataverse_studies',
        ];
    }

    private function getReportService(string $application = DataverseStatsReport::OJS_APP_NAME): DataverseReportService
    {
        return new DataverseReportService($this->context->getId(), $application);
    }

    private function setPublicationDate(int $submissionId, string $datePublished): void
    {
        Capsule::table('publications')
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
        $this->createTestSubmission(STATUS_QUEUED, SUBMISSION_EDITOR_DECISION_ACCEPT);
        $this->createTestSubmission(STATUS_PUBLISHED, SUBMISSION_EDITOR_DECISION_ACCEPT);

        $reportService = $this->getReportService();
        $this->assertEquals(2, $reportService->getAcceptedSubmissionsCount());
        $this->assertEquals(0, $reportService->getDeclinedSubmissionsCount());

        $this->createTestSubmission(STATUS_QUEUED, SUBMISSION_EDITOR_DECISION_DECLINE);
        $this->createTestSubmission(STATUS_DECLINED, SUBMISSION_EDITOR_DECISION_DECLINE);
        $this->createTestSubmission(STATUS_DECLINED, SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE);
        $this->assertEquals(2, $reportService->getDeclinedSubmissionsCount());
    }

    public function testCountPublishedSubmissions(): void
    {
        $this->createTestSubmission(STATUS_PUBLISHED, SUBMISSION_EDITOR_DECISION_ACCEPT);
        $this->createTestSubmission(STATUS_PUBLISHED, SUBMISSION_EDITOR_DECISION_DECLINE);
        $this->createTestSubmission(STATUS_PUBLISHED, SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE);

        $reportService = $this->getReportService();
        $this->assertEquals(3, $reportService->getPublishedSubmissionsCount());
    }

    public function testCountSubmissionsWithDataset(): void
    {
        $this->createTestSubmission(STATUS_DECLINED, SUBMISSION_EDITOR_DECISION_DECLINE, true);
        $this->createTestSubmission(STATUS_QUEUED, SUBMISSION_EDITOR_DECISION_DECLINE, true);
        $this->createTestSubmission(STATUS_QUEUED, SUBMISSION_EDITOR_DECISION_DECLINE, false);
        $this->createTestSubmission(STATUS_QUEUED, SUBMISSION_EDITOR_DECISION_ACCEPT, true);
        $this->createTestSubmission(STATUS_QUEUED, SUBMISSION_EDITOR_DECISION_ACCEPT, false);

        $reportService = $this->getReportService();
        $this->assertEquals(1, $reportService->getAcceptedSubmissionsWithDatasetCount());
        $this->assertEquals(1, $reportService->getDeclinedSubmissionsWithDatasetCount());
    }

    public function testCountPublishedSubmissionsWithDataset(): void
    {
        $this->createTestSubmission(STATUS_PUBLISHED, SUBMISSION_EDITOR_DECISION_ACCEPT, true);
        $this->createTestSubmission(STATUS_PUBLISHED, SUBMISSION_EDITOR_DECISION_DECLINE, true);
        $this->createTestSubmission(STATUS_PUBLISHED, SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE, true);

        $reportService = $this->getReportService();
        $this->assertEquals(3, $reportService->getPublishedSubmissionsWithDatasetCount());
    }

    public function testCountsDeclinedSubmissionsAfterDatasetDeletion(): void
    {
        $declinedSubWithDataset = $this->createTestSubmission(STATUS_DECLINED, SUBMISSION_EDITOR_DECISION_DECLINE, true);
        $declinedSubWithJustLog = $this->createTestSubmission(STATUS_DECLINED, SUBMISSION_EDITOR_DECISION_DECLINE, false);
        $this->addEventLogToSubmission(
            $declinedSubWithJustLog->getId(),
            'plugins.generic.dataverse.log.researchDataDeposited'
        );
        $declinedSubWithTranslatedLog = $this->createTestSubmission(STATUS_DECLINED, SUBMISSION_EDITOR_DECISION_DECLINE, false);
        $this->addEventLogToSubmission(
            $declinedSubWithTranslatedLog->getId(),
            'Research data deposited: doi:10.12345/FKII/1234',
            true
        );
        $declinedSubWithLogAndDataset = $this->createTestSubmission(STATUS_DECLINED, SUBMISSION_EDITOR_DECISION_DECLINE, true);
        $this->addEventLogToSubmission(
            $declinedSubWithLogAndDataset->getId(),
            'plugins.generic.dataverse.log.researchDataDeposited'
        );

        $reportService = $this->getReportService();
        $this->assertEquals(4, $reportService->getDeclinedSubmissionsWithDatasetCount());
    }

    public function testCountsTotalSubmissions(): void
    {
        $this->createTestSubmission(STATUS_QUEUED);
        $this->createTestSubmission(STATUS_QUEUED);
        $this->createTestSubmission(STATUS_QUEUED, null, true);
        $this->createTestSubmission(STATUS_DECLINED, SUBMISSION_EDITOR_DECISION_DECLINE);
        $this->createTestSubmission(STATUS_DECLINED, SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE);
        $this->createTestSubmission(STATUS_PUBLISHED, SUBMISSION_EDITOR_DECISION_ACCEPT, true);
        $this->createTestSubmission(STATUS_PUBLISHED, SUBMISSION_EDITOR_DECISION_DECLINE, false);
        $this->createTestSubmission(STATUS_PUBLISHED, SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE, false);

        $reportService = $this->getReportService();
        $this->assertEquals(8, $reportService->getTotalSubmissionsCount());
    }

    public function testGetsSubmissionsStatementStats(): void
    {
        $firstAcceptedSub = $this->createTestSubmission(STATUS_QUEUED, SUBMISSION_EDITOR_DECISION_ACCEPT);
        $secondAcceptedSub = $this->createTestSubmission(STATUS_PUBLISHED, SUBMISSION_EDITOR_DECISION_ACCEPT);

        $firstDeclinedSub = $this->createTestSubmission(STATUS_DECLINED, SUBMISSION_EDITOR_DECISION_DECLINE);
        $secondDeclinedSub = $this->createTestSubmission(STATUS_DECLINED, SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE);

        $this->addDataStatementTypesToSubmission($firstAcceptedSub, [
            DATA_STATEMENT_TYPE_IN_MANUSCRIPT,
            DATA_STATEMENT_TYPE_REPO_AVAILABLE
        ]);
        $this->addDataStatementTypesToSubmission($secondAcceptedSub, [
            DATA_STATEMENT_TYPE_REPO_AVAILABLE,
            DATA_STATEMENT_TYPE_ON_DEMAND
        ]);
        $this->addDataStatementTypesToSubmission($firstDeclinedSub, [
            DATA_STATEMENT_TYPE_ON_DEMAND,
            DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE
        ]);
        $this->addDataStatementTypesToSubmission($secondDeclinedSub, [
            DATA_STATEMENT_TYPE_ON_DEMAND,
            DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE
        ]);

        $reportService = $this->getReportService();
        $acceptedStatementStats = $reportService->getAcceptedStatementStatistics();
        $declinedStatementStats = $reportService->getDeclinedStatementStatistics();

        $this->assertEquals([1, 2, 1, 0], $acceptedStatementStats->getStats());
        $this->assertEquals([0, 0, 2, 2], $declinedStatementStats->getStats());
    }

    public function testGetsPublishedSubmissionsStatementStats(): void
    {
        $firstPublishedSub = $this->createTestSubmission(STATUS_PUBLISHED, SUBMISSION_EDITOR_DECISION_ACCEPT);
        $secondPublishedSub = $this->createTestSubmission(STATUS_PUBLISHED, SUBMISSION_EDITOR_DECISION_ACCEPT);

        $this->addDataStatementTypesToSubmission($firstPublishedSub, [
            DATA_STATEMENT_TYPE_IN_MANUSCRIPT,
            DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED
        ]);
        $this->addDataStatementTypesToSubmission($secondPublishedSub, [
            DATA_STATEMENT_TYPE_REPO_AVAILABLE,
            DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED
        ]);

        $reportService = $this->getReportService();
        $publishedStatementStats = $reportService->getPublishedStatementStatistics();

        $this->assertEquals([1, 1, 0, 0], $publishedStatementStats->getStats());
    }

    public function testGetsTotalSubmissionsStatementStats(): void
    {
        $firstSub = $this->createTestSubmission(STATUS_PUBLISHED, SUBMISSION_EDITOR_DECISION_ACCEPT);
        $secondSub = $this->createTestSubmission(STATUS_QUEUED);
        $thirdSub = $this->createTestSubmission(STATUS_DECLINED, SUBMISSION_EDITOR_DECISION_DECLINE);

        $this->addDataStatementTypesToSubmission($firstSub, [
            DATA_STATEMENT_TYPE_IN_MANUSCRIPT
        ]);
        $this->addDataStatementTypesToSubmission($secondSub, [
            DATA_STATEMENT_TYPE_IN_MANUSCRIPT,
            DATA_STATEMENT_TYPE_REPO_AVAILABLE
        ]);
        $this->addDataStatementTypesToSubmission($thirdSub, [
            DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE,
        ]);

        $reportService = $this->getReportService();
        $publishedStatementStats = $reportService->getTotalStatementStatistics();

        $this->assertEquals([2, 1, 0, 1], $publishedStatementStats->getStats());
    }

    public function testConsidersDateSubmittedInterval(): void
    {
        $beforePublishedSub = $this->createTestSubmission(STATUS_PUBLISHED, null, false, '2026-06-11');
        $beforeDeclinedSub = $this->createTestSubmission(STATUS_DECLINED, SUBMISSION_EDITOR_DECISION_DECLINE, false, '2026-06-11');
        $withinAcceptedSub = $this->createTestSubmission(STATUS_QUEUED, SUBMISSION_EDITOR_DECISION_ACCEPT, false, '2026-06-13');
        $withinDeclinedSub = $this->createTestSubmission(STATUS_DECLINED, SUBMISSION_EDITOR_DECISION_DECLINE, false, '2026-06-13');
        $withinPublishedSub = $this->createTestSubmission(STATUS_PUBLISHED, null, false, '2026-06-13');
        $afterAcceptedSub = $this->createTestSubmission(STATUS_QUEUED, SUBMISSION_EDITOR_DECISION_ACCEPT, false, '2026-06-16');

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
        $beforeAcceptedSub = $this->createTestSubmission(STATUS_QUEUED);
        $withinAcceptedSub = $this->createTestSubmission(STATUS_QUEUED);
        $afterAcceptedSub = $this->createTestSubmission(STATUS_QUEUED);
        $beforeDeclinedSub = $this->createTestSubmission(STATUS_DECLINED);
        $withinDeclinedSub = $this->createTestSubmission(STATUS_DECLINED);

        $this->addDecision(SUBMISSION_EDITOR_DECISION_ACCEPT, $beforeAcceptedSub->getId(), '2026-06-11 10:00:00');
        $this->addDecision(SUBMISSION_EDITOR_DECISION_ACCEPT, $withinAcceptedSub->getId(), '2026-06-13 10:00:00');
        $this->addDecision(SUBMISSION_EDITOR_DECISION_ACCEPT, $afterAcceptedSub->getId(), '2026-06-16 10:00:00');
        $this->addDecision(SUBMISSION_EDITOR_DECISION_DECLINE, $beforeDeclinedSub->getId(), '2026-06-11 10:00:00');
        $this->addDecision(SUBMISSION_EDITOR_DECISION_DECLINE, $withinDeclinedSub->getId(), '2026-06-14 10:00:00');

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
        $beforePublishedSub = $this->createTestSubmission(STATUS_PUBLISHED);
        $withinPublishedSub = $this->createTestSubmission(STATUS_PUBLISHED);
        $withinDeclinedSub = $this->createTestSubmission(STATUS_DECLINED);
        $withinInitialDeclineSub = $this->createTestSubmission(STATUS_DECLINED);
        $afterDeclinedSub = $this->createTestSubmission(STATUS_DECLINED);

        $this->setPublicationDate($beforePublishedSub->getId(), '2026-06-11');
        $this->setPublicationDate($withinPublishedSub->getId(), '2026-06-13');

        $this->addDecision(SUBMISSION_EDITOR_DECISION_DECLINE, $withinDeclinedSub->getId(), '2026-06-14 10:00:00');
        $this->addDecision(SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE, $withinInitialDeclineSub->getId(), '2026-06-15 10:00:00');
        $this->addDecision(SUBMISSION_EDITOR_DECISION_DECLINE, $afterDeclinedSub->getId(), '2026-06-16 10:00:00');

        $reportService = $this->getReportService(DataverseStatsReport::OPS_APP_NAME);

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
