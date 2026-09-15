<?php

use Illuminate\Support\Facades\DB;
use PKP\tests\DatabaseTestCase;
use APP\core\Application;
use PKP\core\Core;
use PKP\plugins\Hook;
use APP\submission\Submission;
use APP\publication\Publication;
use APP\decision\Decision;
use APP\log\event\SubmissionEventLogEntry;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\classes\dispatchers\DataStatementDispatcher;
use APP\plugins\generic\dataverse\report\services\queryBuilders\DataverseReportQueryBuilder;
use APP\plugins\generic\dataverse\tests\helpers\CreatesTestContext;
use APP\plugins\generic\dataverse\DataversePlugin;

class DataverseReportQueryBuilderTest extends DatabaseTestCase
{
    use CreatesTestContext;

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
        $this->deleteTestContext($this->context);
    }

    private function getQueryBuilder(): DataverseReportQueryBuilder
    {
        return new DataverseReportQueryBuilder();
    }

    private function createTestSubmission($context, $data): Submission
    {
        $submission = new Submission();
        $submission->setAllData($data);
        $submission->setData('contextId', $context->getId());

        $publication = new Publication();

        $submissionId = Repo::submission()->add($submission, $publication, $context);
        $submission->setId($submissionId);

        return $submission;
    }

    private function addPublicationVersion(Submission $submission): void
    {
        DB::table('publications')->insert([
            'submission_id' => $submission->getId(),
            'status' => Submission::STATUS_QUEUED,
            'version' => 2,
            'seq' => 0,
        ]);
    }

    private function addDecision(Submission $submission, int $decision): void
    {
        $decision = Repo::decision()->newDataObject([
            'decision' => $decision,
            'submissionId' => $submission->getId(),
            'dateDecided' => date(Core::getCurrentDate()),
            'editorId' => 1,
        ]);
        Repo::decision()->dao->insert($decision);
    }

    public function testFilterSubmissionByContexts(): void
    {
        $submission = $this->createTestSubmission($this->context, [
            'submissionProgress' => '',
        ]);

        $query = $this->getQueryBuilder()
            ->filterByContexts($this->context->getId())
            ->getQuery();

        $this->assertEquals(
            $submission->getId(),
            $query->get()->first()->submission_id
        );
    }

    public function testIncompleteSubmissionsAreNotRetrieved(): void
    {
        $completeSubmission = $this->createTestSubmission($this->context, [
            'submissionProgress' => '',
        ]);

        $this->createTestSubmission($this->context, [
            'submissionProgress' => 'details',
        ]);

        $query = $this->getQueryBuilder()
            ->filterByContexts($this->context->getId())
            ->getQuery();

        $this->assertEquals(
            [$completeSubmission->getId()],
            $query->get()->pluck('submission_id')->all()
        );
    }

    public function testFilterSubmissionByDecisions(): void
    {
        $acceptedSubmission = $this->createTestSubmission($this->context, [
            'submissionProgress' => '',
        ]);

        $declinedSubmission = $this->createTestSubmission($this->context, [
            'submissionProgress' => '',
            'status' => Submission::STATUS_DECLINED
        ]);

        $this->addDecision($acceptedSubmission, Decision::ACCEPT);
        $this->addDecision($declinedSubmission, Decision::DECLINE);

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

        $this->assertEquals(
            $declinedSubmission->getId(),
            $declinedQuery->get()->first()->submission_id
        );
    }

    public function testVersionedSubmissionIsCountedOnce(): void
    {
        $submission = $this->createTestSubmission($this->context, [
            'submissionProgress' => '',
        ]);
        $this->addPublicationVersion($submission);

        $query = $this->getQueryBuilder()
            ->filterByContexts($this->context->getId())
            ->getQuery();

        $this->assertEquals(1, $query->count());
    }

    public function testSubmissionWithRepeatedDecisionIsCountedOnce(): void
    {
        $submission = $this->createTestSubmission($this->context, [
            'submissionProgress' => '',
            'status' => Submission::STATUS_DECLINED
        ]);

        foreach ([Decision::INITIAL_DECLINE, Decision::DECLINE] as $decision) {
            $this->addDecision($submission, $decision);
        }

        $query = $this->getQueryBuilder()
            ->filterByContexts($this->context->getId())
            ->filterByDecisions([Decision::DECLINE, Decision::INITIAL_DECLINE])
            ->getQuery();

        $this->assertEquals(1, $query->count());
    }

    public function testGetSubmissionsWithDataset(): void
    {
        $submission = $this->createTestSubmission($this->context, [
            'submissionProgress' => '',
        ]);

        $datasetSubmission = $this->createTestSubmission($this->context, [
            'submissionProgress' => '',
        ]);

        $study = Repo::dataverseStudy()->newDataObject();
        $study->setAllData([
            'submissionId' => $datasetSubmission->getId(),
            'persistentId' => 'testId',
            'persistentUri' => 'testUri',
            'editUri' => 'testEditUri',
            'editMediaUri' => 'testEditMediaUri',
            'statementUri' => 'testStatementUri',
        ]);
        Repo::dataverseStudy()->add($study);

        $query = $this->getQueryBuilder()
            ->filterByContexts($this->context->getId())
            ->getWithDataset();

        $this->assertEquals(
            $datasetSubmission->getId(),
            $query->get()->first()->submission_id
        );
    }

    public function testCountDatasetsWithDepositError(): void
    {
        $submission = $this->createTestSubmission($this->context, [
            'submissionProgress' => '',
        ]);

        $depositErrorEntry = Repo::eventLog()->newDataObject([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submission->getId(),
            'eventType' => SubmissionEventLogEntry::SUBMISSION_LOG_METADATA_UPDATE,
            'message' => 'plugins.generic.dataverse.error.datasetDeposit',
            'isTranslated' => false,
            'dateLogged' => Core::getCurrentDate(),
        ]);
        Repo::eventLog()->add($depositErrorEntry);

        $publishErrorEntry = Repo::eventLog()->newDataObject([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submission->getId(),
            'eventType' => SubmissionEventLogEntry::SUBMISSION_LOG_METADATA_UPDATE,
            'message' => 'plugins.generic.dataverse.error.publishFailed',
            'isTranslated' => false,
            'dateLogged' => Core::getCurrentDate(),
        ]);
        Repo::eventLog()->add($publishErrorEntry);

        $depositErrorsCount = $this->getQueryBuilder()
            ->filterByContexts($this->context->getId())
            ->countDatasetsWithError(['plugins.generic.dataverse.error.datasetDeposit']);

        $publishErrorsCount = $this->getQueryBuilder()
            ->filterByContexts($this->context->getId())
            ->countDatasetsWithError(['plugins.generic.dataverse.error.publishFailed']);

        $this->assertEquals(1, $depositErrorsCount);
        $this->assertEquals(1, $publishErrorsCount);
    }
}
