<?php

use PKP\tests\DatabaseTestCase;
use APP\submission\Submission;
use APP\decision\Decision;
use APP\plugins\generic\dataverse\tests\report\traits\ReportTestsHelperTrait;
use APP\plugins\generic\dataverse\report\services\queryBuilders\DataverseReportQueryBuilder;
use APP\plugins\generic\dataverse\report\services\DataverseReportService;
use APP\plugins\generic\dataverse\report\classes\DataverseStatsReport;
use APP\plugins\generic\dataverse\classes\services\DataStatementService;
use Illuminate\Support\Facades\DB;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\dataverseAPI\search\DataverseSearchBuilder;
use APP\publication\Publication;
use APP\core\Application;
use APP\log\event\SubmissionEventLogEntry;

class DataverseReportServiceTest extends DatabaseTestCase
{
    use ReportTestsHelperTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpReportFixture();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->tearDownReportFixture();
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

    public function testCountsDatasetsWithErrorsFromTranslatedEventLogs(): void
    {
        $withDepositError = $this->createTestSubmission(Submission::STATUS_QUEUED);
        $this->addEventLogToSubmission(
            $withDepositError->getId(),
            __('plugins.generic.dataverse.error.datasetDeposit', ['error' => 'Bad request'], 'en'),
            true
        );
        $withFileDepositError = $this->createTestSubmission(Submission::STATUS_QUEUED);
        $this->addEventLogToSubmission(
            $withFileDepositError->getId(),
            'plugins.generic.dataverse.error.datasetFileDeposit'
        );
        $withPublishError = $this->createTestSubmission(Submission::STATUS_QUEUED);
        $this->addEventLogToSubmission(
            $withPublishError->getId(),
            __('plugins.generic.dataverse.error.publishFailed', ['error' => 'Locked'], 'en'),
            true
        );
        $this->createTestSubmission(Submission::STATUS_QUEUED);

        $reportService = $this->getReportService();
        $this->assertEquals(2, $reportService->getDatasetsWithDepositErrorCount());
        $this->assertEquals(1, $reportService->getDatasetsWithPublishErrorCount());
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

    private function mockSearchBuilder(DataverseReportService $reportService, array &$filteredIds): void
    {
        $searchBuilder = $this->createMock(DataverseSearchBuilder::class);
        $searchBuilder->method('addType')->willReturnSelf();
        $searchBuilder->method('addFilterQuery')->willReturnCallback(
            function ($field, $value) use ($searchBuilder, &$filteredIds) {
                $filteredIds[] = $value;
                return $searchBuilder;
            }
        );
        $searchBuilder->method('count')->willReturn(3);
        $reportService->method('getDataverseSearchBuilder')->willReturn($searchBuilder);
    }

    public function testCountsDatasetFilesOnlyOfTheContextDatasets(): void
    {
        $this->createTestSubmission(Submission::STATUS_QUEUED, null, true);
        $otherContext = $this->createTestContext();

        try {
            $otherSubmission = new Submission();
            $otherSubmission->setAllData([
                'submissionProgress' => '',
                'contextId' => $otherContext->getId(),
            ]);
            $otherSubmissionId = Repo::submission()->add($otherSubmission, new Publication(), $otherContext);
            $study = Repo::dataverseStudy()->newDataObject();
            $study->setAllData([
                'submissionId' => $otherSubmissionId,
                'persistentId' => 'otherContextId',
                'persistentUri' => 'otherContextUri',
                'editUri' => 'otherContextEditUri',
                'editMediaUri' => 'otherContextEditMediaUri',
                'statementUri' => 'otherContextStatementUri',
            ]);
            Repo::dataverseStudy()->add($study);

            $reportService = $this->getMockBuilder(DataverseReportService::class)
                ->setConstructorArgs([$this->context->getId(), DataverseStatsReport::OJS_APP_NAME])
                ->onlyMethods(['getDataverseSearchBuilder'])
                ->getMock();
            $filteredIds = [];
            $this->mockSearchBuilder($reportService, $filteredIds);

            $this->assertEquals(3, $reportService->countDatasetFiles());
            $this->assertEquals(['testId'], $filteredIds);
        } finally {
            $this->deleteTestContext($otherContext);
        }
    }

    public function testContextWithoutDatasetsHasNoFilesInDatasets(): void
    {
        $reportService = $this->getMockBuilder(DataverseReportService::class)
            ->setConstructorArgs([$this->context->getId(), DataverseStatsReport::OJS_APP_NAME])
            ->onlyMethods(['getDataverseSearchBuilder'])
            ->getMock();
        $reportService->expects($this->never())->method('getDataverseSearchBuilder');

        $this->assertSame(0, $reportService->countDatasetFiles());
    }

    public function testAcceptedSubmissionSentToProductionKeepsItsFinalDecision(): void
    {
        $submission = $this->createTestSubmission(Submission::STATUS_QUEUED);
        $this->addDecision(Decision::ACCEPT, $submission->getId(), '2026-03-01 10:00:00');
        $this->addDecision(Decision::SEND_TO_PRODUCTION, $submission->getId(), '2026-03-02 10:00:00');

        $reportService = $this->getReportService();
        $reportService->setFinalDecisionDateInterval('2026-01-01 00:00:00', '2026-12-31 23:59:59');

        $this->assertSame(1, $reportService->getAcceptedSubmissionsCount());
        $this->assertSame(1, $reportService->getTotalSubmissionsCount());
    }

    public function testRevertedDeclineIsNotAFinalDecision(): void
    {
        $submission = $this->createTestSubmission(Submission::STATUS_QUEUED);
        $this->addDecision(Decision::DECLINE, $submission->getId(), '2026-03-01 10:00:00');
        $this->addDecision(Decision::REVERT_DECLINE, $submission->getId(), '2026-03-02 10:00:00');

        $reportService = $this->getReportService();
        $reportService->setFinalDecisionDateInterval('2026-01-01 00:00:00', '2026-12-31 23:59:59');

        $this->assertSame(0, $reportService->getTotalSubmissionsCount());
    }

    public function testSubmissionDeclinedAfterAcceptanceIsCountedOnlyAsDeclined(): void
    {
        $submission = $this->createTestSubmission(Submission::STATUS_DECLINED, withDataset: true);
        $this->addDecision(Decision::ACCEPT, $submission->getId(), '2026-03-01 10:00:00');
        $this->addDecision(Decision::DECLINE, $submission->getId(), '2026-03-05 10:00:00');
        $this->addDataStatementTypesToSubmission($submission, [DataStatementService::DATA_STATEMENT_TYPE_IN_MANUSCRIPT]);

        $reportService = $this->getReportService();

        $this->assertSame(0, $reportService->getAcceptedSubmissionsCount());
        $this->assertSame(0, $reportService->getAcceptedSubmissionsWithDatasetCount());
        $this->assertSame([0, 0, 0, 0], $reportService->getAcceptedStatementStatistics()->getStats());
        $this->assertSame(1, $reportService->getDeclinedSubmissionsCount());
    }

    public function testPreprintIsCountedInThePeriodItWasFirstPosted(): void
    {
        $submission = $this->createTestSubmission(Submission::STATUS_PUBLISHED);
        $this->setPublicationDate($submission->getId(), '2025-03-01');
        Repo::publication()->dao->insert(Repo::publication()->newDataObject([
            'submissionId' => $submission->getId(),
            'status' => Submission::STATUS_PUBLISHED,
            'version' => 2,
            'datePublished' => '2026-02-01',
        ]));

        $reportService = $this->getOpsReportService();
        $reportService->setFinalDecisionDateInterval('2026-01-01 00:00:00', '2026-12-31 23:59:59');
        $this->assertSame(0, $reportService->getPublishedSubmissionsCount());

        $reportService->setFinalDecisionDateInterval('2025-01-01 00:00:00', '2025-12-31 23:59:59');
        $this->assertSame(1, $reportService->getPublishedSubmissionsCount());
    }

    public function testDepositErrorsOnlyCountEventsLoggedForSubmissions(): void
    {
        $submission = $this->createTestSubmission(Submission::STATUS_QUEUED);
        Repo::eventLog()->add(Repo::eventLog()->newDataObject([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION_FILE,
            'assocId' => $submission->getId(),
            'eventType' => SubmissionEventLogEntry::SUBMISSION_LOG_SUBMISSION_SUBMIT,
            'message' => 'plugins.generic.dataverse.error.datasetDeposit',
            'isTranslated' => false,
            'dateLogged' => '2026-03-01 10:00:00',
        ]));

        $this->assertSame(0, $this->getReportService()->getDatasetsWithDepositErrorCount());
    }

    public function testEmptyDataStatementTypesDoNotBreakTheStatistics(): void
    {
        $submission = $this->createTestSubmission(Submission::STATUS_QUEUED);
        DB::table('publication_settings')->insert([
            'publication_id' => $submission->getData('currentPublicationId'),
            'locale' => '',
            'setting_name' => 'dataStatementTypes',
            'setting_value' => null,
        ]);

        $this->assertSame([0, 0, 0, 0], $this->getReportService()->getTotalStatementStatistics()->getStats());
    }
}
