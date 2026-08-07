<?php

use PKP\tests\DatabaseTestCase;
use APP\core\Application;
use APP\submission\Submission;
use APP\decision\Decision;
use APP\plugins\generic\dataverse\tests\report\traits\ReportTestsHelperTrait;
use APP\plugins\generic\dataverse\classes\dispatchers\DataStatementDispatcher;
use APP\plugins\generic\dataverse\report\services\queryBuilders\DataverseReportQueryBuilder;
use APP\plugins\generic\dataverse\DataversePlugin;

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

    private function getQueryBuilder(): DataverseReportQueryBuilder
    {
        return new DataverseReportQueryBuilder();
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
        $this->addEventLogToSubmission(
            $submissionErrorDeposit->getId(),
            'plugins.generic.dataverse.error.datasetDeposit'
        );
        $this->addEventLogToSubmission(
            $submissionErrorPublish->getId(),
            'plugins.generic.dataverse.error.publishFailed'
        );

        $depositErrorsCount = $this->getQueryBuilder()
            ->filterByContexts($this->context->getId())
            ->filterWithEventLogs(['plugins.generic.dataverse.error.datasetDeposit'])
            ->getCount();

        $publishErrorsCount = $this->getQueryBuilder()
            ->filterByContexts($this->context->getId())
            ->filterWithEventLogs(['plugins.generic.dataverse.error.publishFailed'])
            ->getCount();

        $this->assertEquals(1, $depositErrorsCount);
        $this->assertEquals(1, $publishErrorsCount);
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
}
