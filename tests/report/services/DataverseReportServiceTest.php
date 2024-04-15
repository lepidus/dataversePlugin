<?php

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
use APP\plugins\generic\dataverse\report\services\DataverseReportService;
use APP\plugins\generic\dataverse\DataversePlugin;

class DataverseReportServiceTest extends DatabaseTestCase
{
    private $context;

    public function setUp(): void
    {
        parent::setUp();
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

    private function createTestSubmission($context, $data): Submission
    {
        $plugin = new DataversePlugin();
        $dispatcher = new DataStatementDispatcher($plugin);

        Hook::add('Schema::get::publication', [$dispatcher, 'addDataStatementToPublicationSchema']);

        $submission = new Submission();
        $submission->setAllData($data);
        $submission->setData('contextId', $context->getId());

        $publication = new Publication();

        $submissionId = Repo::submission()->add($submission, $publication, $context);
        $submission->setId($submissionId);

        return $submission;
    }

    public function testGetQueryBuilder(): void
    {
        $reportService = new DataverseReportService();
        $this->assertInstanceOf(
            DataverseReportQueryBuilder::class,
            $reportService->getQueryBuilder()
        );
    }

    public function testGetReportHeaders(): void
    {
        $reportService = new DataverseReportService();
        $headers = [];

        if (Application::get()->getName() == 'ojs2') {
            $headers = array_merge($headers, [
                __('plugins.generic.dataverse.report.headers.acceptedSubmissions'),
                __('plugins.generic.dataverse.report.headers.acceptedSubmissionsWithDataset'),
            ]);
        }

        $headers = array_merge($headers, [
            __('plugins.generic.dataverse.report.headers.declinedSubmissions'),
            __('plugins.generic.dataverse.report.headers.declinedSubmissionsWithDataset'),
            __('plugins.generic.dataverse.report.headers.datasetsWithDepositError'),
            __('plugins.generic.dataverse.report.headers.datasetsWithPublishError'),
            __('plugins.generic.dataverse.report.headers.filesInDatasets')
        ]);

        $this->assertEquals($headers, $reportService->getReportHeaders());
    }

    public function testCountSubmissions(): void
    {
        $submission = $this->createTestSubmission($this->context, [
            'submissionProgress' => DataverseReportQueryBuilder::SUBMISSION_PROGRESS_COMPLETE,
        ]);

        $acceptDecision = Repo::decision()->newDataObject([
            'decision' => Decision::ACCEPT,
            'submissionId' => $submission->getId(),
            'dateDecided' => date(Core::getCurrentDate()),
            'editorId' => 1,
        ]);
        Repo::decision()->dao->insert($acceptDecision);

        $reportService = new DataverseReportService();
        $acceptedSubmissions = $reportService->countSubmissions([
            'contextIds' => [$this->context->getId()],
            'decisions' => [Decision::ACCEPT],
        ]);
        $declinedSubmissions = $reportService->countSubmissions([
            'contextIds' => [$this->context->getId()],
            'decisions' => [Decision::DECLINE],
        ]);

        $this->assertEquals(1, $acceptedSubmissions);
        $this->assertEquals(0, $declinedSubmissions);
    }

    public function testCountSubmissionsWithDataset(): void
    {
        $submission = $this->createTestSubmission($this->context, [
            'submissionProgress' => DataverseReportQueryBuilder::SUBMISSION_PROGRESS_COMPLETE,
            'status' => Submission::STATUS_DECLINED
        ]);

        $study = Repo::dataverseStudy()->newDataObject();
        $study->setAllData([
            'submissionId' => $submission->getId(),
            'persistentId' => 'testId',
            'persistentUri' => 'testUri',
            'editUri' => 'testEditUri',
            'editMediaUri' => 'testEditMediaUri',
            'statementUri' => 'testStatementUri',
        ]);
        Repo::dataverseStudy()->add($study);

        $declineDecision = Repo::decision()->newDataObject([
            'decision' => Decision::DECLINE,
            'submissionId' => $submission->getId(),
            'dateDecided' => date(Core::getCurrentDate()),
            'editorId' => 1,
        ]);
        Repo::decision()->dao->insert($declineDecision);

        $reportService = new DataverseReportService();
        $this->assertEquals(1, $reportService->countSubmissionsWithDataset([
            'contextIds' => [$this->context->getId()],
            'decisions' => [Decision::DECLINE],
        ]));
    }
}
