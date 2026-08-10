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
        $this->createTestSubmission(Submission::STATUS_PUBLISHED, Decision::ACCEPT);

        $reportService = new DataverseReportService($this->context->getId());
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

        $reportService = new DataverseReportService($this->context->getId());
        $this->assertEquals(3, $reportService->getPublishedSubmissionsCount());
    }

    public function testCountSubmissionsWithDataset(): void
    {
        $this->createTestSubmission(Submission::STATUS_DECLINED, Decision::DECLINE, true);
        $this->createTestSubmission(Submission::STATUS_QUEUED, Decision::DECLINE, true);
        $this->createTestSubmission(Submission::STATUS_QUEUED, Decision::DECLINE, false);
        $this->createTestSubmission(Submission::STATUS_QUEUED, Decision::ACCEPT, true);
        $this->createTestSubmission(Submission::STATUS_QUEUED, Decision::ACCEPT, false);

        $reportService = new DataverseReportService($this->context->getId());
        $this->assertEquals(1, $reportService->getAcceptedSubmissionsWithDatasetCount());
        $this->assertEquals(1, $reportService->getDeclinedSubmissionsWithDatasetCount());
    }

    public function testCountPublishedSubmissionsWithDataset(): void
    {
        $this->createTestSubmission(Submission::STATUS_PUBLISHED, Decision::ACCEPT, true);
        $this->createTestSubmission(Submission::STATUS_PUBLISHED, Decision::DECLINE, true);
        $this->createTestSubmission(Submission::STATUS_PUBLISHED, Decision::INITIAL_DECLINE, true);

        $reportService = new DataverseReportService($this->context->getId());
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

        $reportService = new DataverseReportService($this->context->getId());
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

        $reportService = new DataverseReportService($this->context->getId());
        $this->assertEquals(8, $reportService->getTotalSubmissionsCount());
    }
}
