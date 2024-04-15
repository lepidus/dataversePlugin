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
use APP\plugins\generic\dataverse\DataversePlugin;

class DataverseReportQueryBuilderTest extends DatabaseTestCase
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

    private function getQueryBuilder(): DataverseReportQueryBuilder
    {
        return new DataverseReportQueryBuilder();
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
        $submission = new Submission();
        $submission->setAllData($data);
        $submission->setData('contextId', $context->getId());

        $publication = new Publication();

        $submissionId = Repo::submission()->add($submission, $publication, $context);
        $submission->setId($submissionId);

        return $submission;
    }

    public function testFilterSubmissionByContexts(): void
    {
        $submission = $this->createTestSubmission($this->context, [
            'submissionProgress' => DataverseReportQueryBuilder::SUBMISSION_PROGRESS_COMPLETE,
        ]);

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
        $acceptedSubmission = $this->createTestSubmission($this->context, [
            'submissionProgress' => DataverseReportQueryBuilder::SUBMISSION_PROGRESS_COMPLETE,
        ]);

        $declinedSubmission = $this->createTestSubmission($this->context, [
            'submissionProgress' => DataverseReportQueryBuilder::SUBMISSION_PROGRESS_COMPLETE,
            'status' => Submission::STATUS_DECLINED
        ]);

        $acceptDecision = Repo::decision()->newDataObject([
            'decision' => Decision::ACCEPT,
            'submissionId' => $acceptedSubmission->getId(),
            'dateDecided' => date(Core::getCurrentDate()),
            'editorId' => 1,
        ]);
        Repo::decision()->dao->insert($acceptDecision);

        $declineDecision = Repo::decision()->newDataObject([
            'decision' => Decision::DECLINE,
            'submissionId' => $declinedSubmission->getId(),
            'dateDecided' => date(Core::getCurrentDate()),
            'editorId' => 1,
        ]);
        Repo::decision()->dao->insert($declineDecision);

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

    public function testGetSubmissionsWithDataset(): void
    {
        $submission = $this->createTestSubmission($this->context, [
            'submissionProgress' => DataverseReportQueryBuilder::SUBMISSION_PROGRESS_COMPLETE,
        ]);

        $datasetSubmission = $this->createTestSubmission($this->context, [
            'submissionProgress' => DataverseReportQueryBuilder::SUBMISSION_PROGRESS_COMPLETE,
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
            'submissionProgress' => DataverseReportQueryBuilder::SUBMISSION_PROGRESS_COMPLETE,
        ]);

        $depositErrorEntry = Repo::eventLog()->newDataObject([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submission->getId(),
            'eventType' => SubmissionEventLogEntry::SUBMISSION_LOG_METADATA_UPDATE,
            'message' => 'plugins.generic.dataverse.error.depositFailed',
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
            ->countDatasetsWithError(['plugins.generic.dataverse.error.depositFailed']);

        $publishErrorsCount = $this->getQueryBuilder()
            ->filterByContexts($this->context->getId())
            ->countDatasetsWithError(['plugins.generic.dataverse.error.publishFailed']);

        $this->assertEquals(1, $depositErrorsCount);
        $this->assertEquals(1, $publishErrorsCount);
    }
}
