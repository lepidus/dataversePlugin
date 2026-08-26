<?php

use Illuminate\Database\Capsule\Manager as Capsule;

import('lib.pkp.tests.DatabaseTestCase');
import('plugins.generic.dataverse.report.services.queryBuilders.DataverseReportQueryBuilder');
import('plugins.generic.dataverse.tests.report.traits.ReportTestsHelperTrait');
import('plugins.generic.dataverse.DataversePlugin');
import('plugins.generic.dataverse.classes.dispatchers.DataStatementDispatcher');

class DataverseReportQueryBuilderTest extends DatabaseTestCase
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
            'event_log'
        ];
    }

    private function getQueryBuilder($applicationName = 'ojs2'): DataverseReportQueryBuilder
    {
        return new DataverseReportQueryBuilder($applicationName);
    }

    private function setPublicationDate(int $submissionId, string $datePublished): void
    {
        Capsule::table('publications')
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
        $acceptedSubmission = $this->createTestSubmission(STATUS_QUEUED, SUBMISSION_EDITOR_DECISION_ACCEPT);
        $declinedSubmission = $this->createTestSubmission(STATUS_DECLINED, SUBMISSION_EDITOR_DECISION_DECLINE);
        $queuedWithDeclineDecisionSub = $this->createTestSubmission(STATUS_QUEUED, SUBMISSION_EDITOR_DECISION_DECLINE);
        $publishedWithDeclineDecisionSub = $this->createTestSubmission(STATUS_PUBLISHED, SUBMISSION_EDITOR_DECISION_DECLINE);

        $query = $this->getQueryBuilder()
            ->filterByContexts($this->context->getId());

        $acceptedQuery = $query->filterByDecisions([SUBMISSION_EDITOR_DECISION_ACCEPT])
            ->getQuery();

        $declinedQuery = $query->filterByDecisions([SUBMISSION_EDITOR_DECISION_DECLINE])
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
        $submissionWithDataset = $this->createTestSubmission(STATUS_QUEUED, null, true);
        $submissionWithoutDataset = $this->createTestSubmission(STATUS_QUEUED, null, false);

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
        $submissionErrorDeposit = $this->createTestSubmission(STATUS_QUEUED, null, true);
        $submissionErrorPublish = $this->createTestSubmission(STATUS_QUEUED, null, true);
        $submissionWithTranslatedLog = $this->createTestSubmission(STATUS_QUEUED, null, true);
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
        $publishedNoDecisionSub = $this->createTestSubmission(STATUS_PUBLISHED);
        $publishedAcceptDecisionSub = $this->createTestSubmission(STATUS_PUBLISHED, SUBMISSION_EDITOR_DECISION_ACCEPT);
        $publishedDeclineDecisionSub = $this->createTestSubmission(STATUS_PUBLISHED, SUBMISSION_EDITOR_DECISION_DECLINE);

        $publishedSubmissionIds = $this->getQueryBuilder()
            ->filterByContexts($this->context->getId())
            ->filterByStatuses([STATUS_PUBLISHED])
            ->getSubmissionIds();

        $this->assertTrue(in_array($publishedNoDecisionSub->getId(), $publishedSubmissionIds));
        $this->assertTrue(in_array($publishedAcceptDecisionSub->getId(), $publishedSubmissionIds));
        $this->assertTrue(in_array($publishedDeclineDecisionSub->getId(), $publishedSubmissionIds));
    }

    public function testGetsSubmissionsDataStatementTypes(): void
    {
        $publishedSubmission = $this->createTestSubmission(STATUS_PUBLISHED);
        $acceptedSubmission = $this->createTestSubmission(STATUS_QUEUED, SUBMISSION_EDITOR_DECISION_ACCEPT);

        $publishedSubmissionTypes = [
            DATA_STATEMENT_TYPE_IN_MANUSCRIPT,
            DATA_STATEMENT_TYPE_REPO_AVAILABLE
        ];
        $acceptedSubmissionTypes = [
            DATA_STATEMENT_TYPE_ON_DEMAND,
            DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE
        ];

        $this->addDataStatementTypesToSubmission($publishedSubmission, $publishedSubmissionTypes);
        $this->addDataStatementTypesToSubmission($acceptedSubmission, $acceptedSubmissionTypes);

        $retrievedPublishedStatementTypes = $this->getQueryBuilder()
            ->filterByContexts($this->context->getId())
            ->filterByStatuses([STATUS_PUBLISHED])
            ->getDataStatementTypes();

        $retrievedAcceptedStatementTypes = $this->getQueryBuilder()
            ->filterByContexts($this->context->getId())
            ->filterByStatuses([STATUS_QUEUED])
            ->filterByDecisions([SUBMISSION_EDITOR_DECISION_ACCEPT])
            ->getDataStatementTypes();

        $this->assertEquals($publishedSubmissionTypes, $retrievedPublishedStatementTypes[0]);
        $this->assertEquals($acceptedSubmissionTypes, $retrievedAcceptedStatementTypes[0]);
    }

    public function testGetsSubmissionsWithinDateSubmittedInterval(): void
    {
        $beforeIntervalSub = $this->createTestSubmission(STATUS_PUBLISHED, null, false, '2026-06-11');
        $withinIntervalSub = $this->createTestSubmission(STATUS_QUEUED, SUBMISSION_EDITOR_DECISION_ACCEPT, false, '2026-06-13');
        $afterIntervalSub = $this->createTestSubmission(STATUS_QUEUED, SUBMISSION_EDITOR_DECISION_ACCEPT, false, '2026-06-16');

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

        $this->addDecision(SUBMISSION_EDITOR_DECISION_EXTERNAL_REVIEW, $acceptedFinalDecisionSub->getId(), '2026-06-11 10:00:00');
        $this->addDecision(SUBMISSION_EDITOR_DECISION_ACCEPT, $acceptedFinalDecisionSub->getId(), '2026-06-13 10:00:00');

        $this->addDecision(SUBMISSION_EDITOR_DECISION_EXTERNAL_REVIEW, $declinedFinalDecisionSub->getId(), '2026-06-11 10:00:00');
        $this->addDecision(SUBMISSION_EDITOR_DECISION_DECLINE, $declinedFinalDecisionSub->getId(), '2026-06-14 10:00:00');

        $this->addDecision(SUBMISSION_EDITOR_DECISION_EXTERNAL_REVIEW, $initialDeclineFinalDecisionSub->getId(), '2026-06-11 10:00:00');
        $this->addDecision(SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE, $initialDeclineFinalDecisionSub->getId(), '2026-06-15 10:00:00');

        $this->addDecision(SUBMISSION_EDITOR_DECISION_ACCEPT, $historicalFinalDecisionSub->getId(), '2026-06-13 10:00:00');
        $this->addDecision(SUBMISSION_EDITOR_DECISION_EXTERNAL_REVIEW, $historicalFinalDecisionSub->getId(), '2026-06-14 10:00:00');

        $this->addDecision(SUBMISSION_EDITOR_DECISION_ACCEPT, $outsideIntervalFinalDecisionSub->getId(), '2026-06-11 10:00:00');

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
        $publishedWithinSub = $this->createTestSubmission(STATUS_PUBLISHED);
        $declinedWithinSub = $this->createTestSubmission(STATUS_DECLINED);
        $initialDeclinedWithinSub = $this->createTestSubmission(STATUS_DECLINED);
        $publishedBeforeSub = $this->createTestSubmission(STATUS_PUBLISHED);
        $declinedAfterSub = $this->createTestSubmission(STATUS_DECLINED);

        $this->setPublicationDate($publishedWithinSub->getId(), '2026-06-13');
        $this->setPublicationDate($publishedBeforeSub->getId(), '2026-06-11');

        $this->addDecision(SUBMISSION_EDITOR_DECISION_DECLINE, $declinedWithinSub->getId(), '2026-06-14 10:00:00');
        $this->addDecision(SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE, $initialDeclinedWithinSub->getId(), '2026-06-15 10:00:00');
        $this->addDecision(SUBMISSION_EDITOR_DECISION_DECLINE, $declinedAfterSub->getId(), '2026-06-16 10:00:00');

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
