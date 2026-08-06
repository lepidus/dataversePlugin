<?php

use PKP\tests\DatabaseTestCase;
use APP\core\Application;
use PKP\core\Core;
use APP\submission\Submission;
use APP\publication\Publication;
use APP\decision\Decision;
use APP\log\event\SubmissionEventLogEntry;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\classes\dispatchers\DataStatementDispatcher;
use APP\plugins\generic\dataverse\report\services\queryBuilders\DataverseReportQueryBuilder;
use APP\plugins\generic\dataverse\report\services\DataverseReportService;
use APP\plugins\generic\dataverse\DataversePlugin;

class DataverseReportServiceTest extends DatabaseTestCase
{
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

    private function createTestContext()
    {
        $contextDAO = Application::getContextDAO();
        $context = $contextDAO->newDataObject();
        $context->setPath('test');
        $context->setPrimaryLocale('en');
        $id = $contextDAO->insertObject($context);
        $context->setId($id);

        return $context;
    }

    private function addDecision(int $decisionType, int $submissionId)
    {
        $decision = Repo::decision()->newDataObject([
            'decision' => $decisionType,
            'submissionId' => $submissionId,
            'dateDecided' => date(Core::getCurrentDate()),
            'editorId' => 1,
        ]);
        Repo::decision()->dao->insert($decision);
    }

    private function addStudy(int $submissionId)
    {
        $study = Repo::dataverseStudy()->newDataObject();
        $study->setAllData([
            'submissionId' => $submissionId,
            'persistentId' => 'testId',
            'persistentUri' => 'testUri',
            'editUri' => 'testEditUri',
            'editMediaUri' => 'testEditMediaUri',
            'statementUri' => 'testStatementUri',
        ]);
        Repo::dataverseStudy()->add($study);
    }

    private function createTestSubmission(int $status, ?int $decision = null, bool $withDataset = false): Submission
    {
        $submission = new Submission();
        $submission->setAllData([
            'submissionProgress' => DataverseReportQueryBuilder::SUBMISSION_PROGRESS_COMPLETE,
            'contextId' => $this->context->getId(),
            'status' => $status
        ]);

        $publication = new Publication();

        $submissionId = Repo::submission()->add($submission, $publication, $this->context);
        $submission->setId($submissionId);

        if ($decision) {
            $this->addDecision($decision, $submission->getId());
        }

        if ($withDataset) {
            $this->addStudy($submission->getId());
        }

        return $submission;
    }

    private function addEventLogToSubmission(int $submissionId, string $message)
    {
        $eventLog = Repo::eventLog()->newDataObject();
        $eventLog->setAllData([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submissionId,
            'eventType' => SubmissionEventLogEntry::SUBMISSION_LOG_SUBMISSION_SUBMIT,
            'message' => $message,
            'isTranslated' => false,
            'dateLogged' => Core::getCurrentDate()
        ]);
        Repo::eventLog()->add($eventLog);
    }

    public function testGetQueryBuilder(): void
    {
        $reportService = new DataverseReportService($this->context->getId());
        $this->assertInstanceOf(
            DataverseReportQueryBuilder::class,
            $reportService->getQueryBuilder()
        );
    }

    public function testCountSubmissions(): void
    {
        $this->createTestSubmission(Submission::STATUS_QUEUED, Decision::ACCEPT);

        $reportService = new DataverseReportService($this->context->getId());
        $this->assertEquals(1, $reportService->getAcceptedSubmissionsCount());
        $this->assertEquals(0, $reportService->getDeclinedSubmissionsCount());

        $this->createTestSubmission(Submission::STATUS_QUEUED, Decision::DECLINE);
        $this->assertEquals(0, $reportService->getDeclinedSubmissionsCount());
    }

    public function testCountSubmissionsWithDataset(): void
    {
        $this->createTestSubmission(Submission::STATUS_DECLINED, Decision::DECLINE, true);
        $this->createTestSubmission(Submission::STATUS_QUEUED, Decision::DECLINE, false);
        $this->createTestSubmission(Submission::STATUS_QUEUED, Decision::ACCEPT, true);

        $reportService = new DataverseReportService($this->context->getId());
        $this->assertEquals(1, $reportService->getAcceptedSubmissionsWithDatasetCount());
        $this->assertEquals(1, $reportService->getDeclinedSubmissionsWithDatasetCount());
    }

    public function testCountsDeclinedSubmissionsAfterDatasetDeletion(): void
    {
        $declinedSubmission = $this->createTestSubmission(Submission::STATUS_DECLINED, Decision::DECLINE, true);
        $declinedSubmissionWhichHadDataset = $this->createTestSubmission(Submission::STATUS_DECLINED, Decision::DECLINE, false);
        $this->addEventLogToSubmission(
            $declinedSubmissionWhichHadDataset->getId(),
            'plugins.generic.dataverse.log.researchDataDeposited'
        );

        $reportService = new DataverseReportService($this->context->getId());
        $this->assertEquals(2, $reportService->getDeclinedSubmissionsWithDatasetCount());
    }
}
