<?php

use PKP\tests\DatabaseTestCase;
use APP\core\Application;
use APP\submission\Submission;
use APP\decision\Decision;
use APP\plugins\generic\dataverse\tests\report\traits\ReportTestsHelperTrait;
use APP\plugins\generic\dataverse\classes\dispatchers\DataStatementDispatcher;
use APP\plugins\generic\dataverse\report\services\queryBuilders\DataverseReportQueryBuilder;
use APP\plugins\generic\dataverse\classes\services\DataStatementService;
use APP\plugins\generic\dataverse\DataversePlugin;
use Illuminate\Support\Facades\DB;

class DataverseReportQueryBuilderTest extends DatabaseTestCase
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

    private function getQueryBuilder($applicationName = 'ojs2'): DataverseReportQueryBuilder
    {
        return new DataverseReportQueryBuilder($applicationName);
    }

    private function setPublicationDate(int $submissionId, string $datePublished): void
    {
        DB::table('publications')
            ->where('submission_id', '=', $submissionId)
            ->update(['date_published' => $datePublished]);
    }

    public function testFilterSubmissionByContexts(): void
    {
        $submission = $this->createTestSubmission();

        $query = $this->getQueryBuilder()
            ->filterByContexts($this->context->getId())
            ->getQuery();

        $this->assertEquals(
            $submission->getId(),
            $query->get()->first()->submission_id
        );
    }

    public function testFilterSubmissionByDecisions(): void
    {
        $acceptedSubmission = $this->createTestSubmission(Submission::STATUS_QUEUED, Decision::ACCEPT);
        $declinedSubmission = $this->createTestSubmission(Submission::STATUS_DECLINED, Decision::DECLINE);
        $queuedWithDeclineDecisionSub = $this->createTestSubmission(Submission::STATUS_QUEUED, Decision::DECLINE);
        $publishedWithDeclineDecisionSub = $this->createTestSubmission(Submission::STATUS_PUBLISHED, Decision::DECLINE);

        $query = $this->getQueryBuilder()
            ->filterByContexts($this->context->getId());

        $acceptedQuery = $query->filterByDecisions([Decision::ACCEPT])
            ->getQuery();

        $declinedQuery = $query->filterByDecisions([Decision::DECLINE])
            ->getQuery();

        $this->assertEquals(
            $acceptedSubmission->getId(),
            $acceptedQuery->get()->first()->submission_id
        );
        $this->assertEquals(1, $acceptedQuery->count());

        $this->assertEquals(
            $declinedSubmission->getId(),
            $declinedQuery->get()->first()->submission_id
        );
        $this->assertEquals(3, $declinedQuery->count());
    }

    public function testFilterSubmissionsWithDataset(): void
    {
        $submissionWithDataset = $this->createTestSubmission(Submission::STATUS_QUEUED, null, true);
        $submissionWithoutDataset = $this->createTestSubmission(Submission::STATUS_QUEUED, null, false);

        $query = $this->getQueryBuilder()
            ->filterByContexts($this->context->getId())
            ->filterWithDataset()
            ->getQuery();

        $this->assertEquals(
            $submissionWithDataset->getId(),
            $query->get()->first()->submission_id
        );
        $this->assertEquals(1, $query->count());
    }

    public function testFiltersSubmissionsWithEventLogs(): void
    {
        $submissionErrorDeposit = $this->createTestSubmission(Submission::STATUS_QUEUED, null, true);
        $submissionErrorPublish = $this->createTestSubmission(Submission::STATUS_QUEUED, null, true);
        $submissionWithTranslatedLog = $this->createTestSubmission(Submission::STATUS_QUEUED, null, true);
        $this->addEventLogToSubmission(
            $submissionErrorDeposit->getId(),
            'plugins.generic.dataverse.error.datasetDeposit'
        );
        $this->addEventLogToSubmission(
            $submissionErrorPublish->getId(),
            'plugins.generic.dataverse.error.publishFailed'
        );
        $this->addEventLogToSubmission(
            $submissionWithTranslatedLog->getId(),
            'Dataset deposited: doi:10.1234'
        );

        $depositErrorsCount = $this->getQueryBuilder()
            ->filterByContexts($this->context->getId())
            ->filterWithEventLogs(['plugins.generic.dataverse.error.datasetDeposit'])
            ->getCount();

        $publishErrorsCount = $this->getQueryBuilder()
            ->filterByContexts($this->context->getId())
            ->filterWithEventLogs(['plugins.generic.dataverse.error.publishFailed'])
            ->getCount();

        $translatedLogsCount = $this->getQueryBuilder()
            ->filterByContexts($this->context->getId())
            ->filterWithEventLogs(['Dataset deposited'])
            ->getCount();

        $this->assertEquals(1, $depositErrorsCount);
        $this->assertEquals(1, $publishErrorsCount);
        $this->assertEquals(1, $translatedLogsCount);
    }

    public function testFilterSubmissionsByStatus(): void
    {
        $publishedNoDecisionSub = $this->createTestSubmission(Submission::STATUS_PUBLISHED);
        $publishedAcceptDecisionSub = $this->createTestSubmission(Submission::STATUS_PUBLISHED, Decision::ACCEPT);
        $publishedDeclineDecisionSub = $this->createTestSubmission(Submission::STATUS_PUBLISHED, Decision::DECLINE);

        $publishedSubmissionIds = $this->getQueryBuilder()
            ->filterByContexts($this->context->getId())
            ->filterByStatuses([Submission::STATUS_PUBLISHED])
            ->getSubmissionIds();

        $this->assertTrue(in_array($publishedNoDecisionSub->getId(), $publishedSubmissionIds));
        $this->assertTrue(in_array($publishedAcceptDecisionSub->getId(), $publishedSubmissionIds));
        $this->assertTrue(in_array($publishedDeclineDecisionSub->getId(), $publishedSubmissionIds));
    }

    public function testGetsSubmissionsDataStatementTypes(): void
    {
        $publishedSubmission = $this->createTestSubmission(Submission::STATUS_PUBLISHED);
        $acceptedSubmission = $this->createTestSubmission(Submission::STATUS_QUEUED, Decision::ACCEPT);

        $publishedSubmissionTypes = [
            DataStatementService::DATA_STATEMENT_TYPE_IN_MANUSCRIPT,
            DataStatementService::DATA_STATEMENT_TYPE_REPO_AVAILABLE
        ];
        $acceptedSubmissionTypes = [
            DataStatementService::DATA_STATEMENT_TYPE_ON_DEMAND,
            DataStatementService::DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE
        ];

        $this->addDataStatementTypesToSubmission($publishedSubmission, $publishedSubmissionTypes);
        $this->addDataStatementTypesToSubmission($acceptedSubmission, $acceptedSubmissionTypes);

        $retrievedPublishedStatementTypes = $this->getQueryBuilder()
            ->filterByContexts($this->context->getId())
            ->filterByStatuses([Submission::STATUS_PUBLISHED])
            ->getDataStatementTypes();

        $retrievedAcceptedStatementTypes = $this->getQueryBuilder()
            ->filterByContexts($this->context->getId())
            ->filterByStatuses([Submission::STATUS_QUEUED])
            ->filterByDecisions([Decision::ACCEPT])
            ->getDataStatementTypes();

        $this->assertEquals($publishedSubmissionTypes, $retrievedPublishedStatementTypes[0]);
        $this->assertEquals($acceptedSubmissionTypes, $retrievedAcceptedStatementTypes[0]);
    }

    public function testGetsSubmissionsWithinDateSubmittedInterval(): void
    {
        $beforeIntervalSub = $this->createTestSubmission(Submission::STATUS_PUBLISHED, null, false, '2026-06-11');
        $withinIntervalSub = $this->createTestSubmission(Submission::STATUS_QUEUED, Decision::ACCEPT, false, '2026-06-13');
        $afterIntervalSub = $this->createTestSubmission(Submission::STATUS_QUEUED, Decision::ACCEPT, false, '2026-06-16');

        $startInterval = '2026-06-12';
        $endInterval = '2026-06-15';

        $submissionIds = $this->getQueryBuilder()
            ->withinDateSubmittedInterval($startInterval, $endInterval)
            ->getSubmissionIds();

        $this->assertContains($withinIntervalSub->getId(), $submissionIds);
        $this->assertNotContains($beforeIntervalSub->getId(), $submissionIds);
        $this->assertNotContains($afterIntervalSub->getId(), $submissionIds);
    }

    public function testGetsSubmissionsWithFinalDecisionWithinDateIntervalInOjs(): void
    {
        $acceptedFinalDecisionSub = $this->createTestSubmission();
        $declinedFinalDecisionSub = $this->createTestSubmission();
        $initialDeclineFinalDecisionSub = $this->createTestSubmission();
        $historicalFinalDecisionSub = $this->createTestSubmission();
        $outsideIntervalFinalDecisionSub = $this->createTestSubmission();

        $this->addDecision(Decision::EXTERNAL_REVIEW, $acceptedFinalDecisionSub->getId(), '2026-06-11 10:00:00');
        $this->addDecision(Decision::ACCEPT, $acceptedFinalDecisionSub->getId(), '2026-06-13 10:00:00');

        $this->addDecision(Decision::EXTERNAL_REVIEW, $declinedFinalDecisionSub->getId(), '2026-06-11 10:00:00');
        $this->addDecision(Decision::DECLINE, $declinedFinalDecisionSub->getId(), '2026-06-14 10:00:00');

        $this->addDecision(Decision::EXTERNAL_REVIEW, $initialDeclineFinalDecisionSub->getId(), '2026-06-11 10:00:00');
        $this->addDecision(Decision::INITIAL_DECLINE, $initialDeclineFinalDecisionSub->getId(), '2026-06-15 10:00:00');

        $this->addDecision(Decision::ACCEPT, $historicalFinalDecisionSub->getId(), '2026-06-13 10:00:00');
        $this->addDecision(Decision::EXTERNAL_REVIEW, $historicalFinalDecisionSub->getId(), '2026-06-14 10:00:00');

        $this->addDecision(Decision::ACCEPT, $outsideIntervalFinalDecisionSub->getId(), '2026-06-11 10:00:00');

        $submissionIds = $this->getQueryBuilder()
            ->filterByContexts($this->context->getId())
            ->withinFinalDecisionDateInterval(
                '2026-06-12 00:00:00',
                '2026-06-15 23:59:59'
            )
            ->getSubmissionIds();

        $this->assertContains($acceptedFinalDecisionSub->getId(), $submissionIds);
        $this->assertContains($declinedFinalDecisionSub->getId(), $submissionIds);
        $this->assertContains($initialDeclineFinalDecisionSub->getId(), $submissionIds);
        $this->assertNotContains($historicalFinalDecisionSub->getId(), $submissionIds);
        $this->assertNotContains($outsideIntervalFinalDecisionSub->getId(), $submissionIds);
    }

    public function testGetsSubmissionsWithFinalDecisionWithinDateIntervalInOps(): void
    {
        $publishedWithinSub = $this->createTestSubmission(Submission::STATUS_PUBLISHED);
        $declinedWithinSub = $this->createTestSubmission(Submission::STATUS_DECLINED);
        $initialDeclinedWithinSub = $this->createTestSubmission(Submission::STATUS_DECLINED);
        $publishedBeforeSub = $this->createTestSubmission(Submission::STATUS_PUBLISHED);
        $declinedAfterSub = $this->createTestSubmission(Submission::STATUS_DECLINED);

        $this->setPublicationDate($publishedWithinSub->getId(), '2026-06-13');
        $this->setPublicationDate($publishedBeforeSub->getId(), '2026-06-11');

        $this->addDecision(Decision::DECLINE, $declinedWithinSub->getId(), '2026-06-14 10:00:00');
        $this->addDecision(Decision::INITIAL_DECLINE, $initialDeclinedWithinSub->getId(), '2026-06-15 10:00:00');
        $this->addDecision(Decision::DECLINE, $declinedAfterSub->getId(), '2026-06-16 10:00:00');

        $submissionIds = $this->getQueryBuilder('ops')
            ->filterByContexts($this->context->getId())
            ->withinFinalDecisionDateInterval(
                '2026-06-12 00:00:00',
                '2026-06-15 23:59:59'
            )
            ->getSubmissionIds();

        $this->assertContains($publishedWithinSub->getId(), $submissionIds);
        $this->assertContains($declinedWithinSub->getId(), $submissionIds);
        $this->assertContains($initialDeclinedWithinSub->getId(), $submissionIds);
        $this->assertNotContains($publishedBeforeSub->getId(), $submissionIds);
        $this->assertNotContains($declinedAfterSub->getId(), $submissionIds);
    }
}
