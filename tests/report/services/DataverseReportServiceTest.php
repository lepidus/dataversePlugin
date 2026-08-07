<?php

use PKP\tests\DatabaseTestCase;
use APP\core\Application;
use APP\submission\Submission;
use APP\decision\Decision;
use APP\plugins\generic\dataverse\tests\report\traits\ReportTestsHelperTrait;
use APP\plugins\generic\dataverse\classes\dispatchers\DataStatementDispatcher;
use APP\plugins\generic\dataverse\report\services\queryBuilders\DataverseReportQueryBuilder;
use APP\plugins\generic\dataverse\report\services\DataverseReportService;
use APP\plugins\generic\dataverse\DataversePlugin;

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
        $declinedSubWithDataset = $this->createTestSubmission(Submission::STATUS_DECLINED, Decision::DECLINE, true);
        $declinedSubWithLog = $this->createTestSubmission(Submission::STATUS_DECLINED, Decision::DECLINE, false);
        $this->addEventLogToSubmission(
            $declinedSubWithLog->getId(),
            'plugins.generic.dataverse.log.researchDataDeposited'
        );
        $declinedSubWithLogAndDataset = $this->createTestSubmission(Submission::STATUS_DECLINED, Decision::DECLINE, true);
        $this->addEventLogToSubmission(
            $declinedSubWithLogAndDataset->getId(),
            'plugins.generic.dataverse.log.researchDataDeposited'
        );

        $reportService = new DataverseReportService($this->context->getId());
        $this->assertEquals(3, $reportService->getDeclinedSubmissionsWithDatasetCount());
    }
}
