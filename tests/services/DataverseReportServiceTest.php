<?php

import('lib.pkp.tests.DatabaseTestCase');
import('plugins.generic.dataverse.classes.services.DataverseReportService');

class DataverseReportServiceTest extends DatabaseTestCase
{
    protected function getAffectedTables(): array
    {
        return [
            'publications', 'publication_settings',
            'submissions', 'submission_settings',
            'journals', 'journal_settings',
            'edit_decisions',
            'dataverse_studies',
        ];
    }

    private function createTestContext(): int
    {
        $contextDAO = Application::getContextDAO();
        $context = $contextDAO->newDataObject();
        $context->setPath('test');
        $context->setPrimaryLocale('en_US');
        return $contextDAO->insertObject($context);
    }

    private function createTestSubmission(array $data): Submission
    {
        $plugin = new DataversePlugin();
        $dispatcher = new DataStatementDispatcher($plugin);

        HookRegistry::register(
            'Schema::get::publication',
            [$dispatcher, 'addDataStatementToPublicationSchema']
        );

        $submission = DAORegistry::getDAO('SubmissionDAO')->newDataObject();
        $submission->setAllData($data);
        DAORegistry::getDAO('SubmissionDAO')->insertObject($submission);

        $publication = DAORegistry::getDAO('PublicationDAO')->newDataObject();
        $publication->setData('submissionId', $submission->getId());
        DAORegistry::getDAO('PublicationDAO')->insertObject($publication);

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
        $this->assertEquals([
            '##plugins.reports.dataverse.headers.acceptedSubmissions##',
            '##plugins.reports.dataverse.headers.acceptedSubmissionsWithDataset##',
            '##plugins.reports.dataverse.headers.declinedSubmissions##',
            '##plugins.reports.dataverse.headers.declinedSubmissionsWithDataset##',
        ], $reportService->getReportHeaders());
    }

    public function testCountSubmissions(): void
    {
        $contextId = $this->createTestContext();
        $submission = $this->createTestSubmission([
            'contextId' => $contextId,
            'submissionProgress' => SUBMISSION_PROGRESS_COMPLETE,
        ]);

        DAORegistry::getDAO('EditDecisionDAO')->updateEditorDecision($submission->getId(), [
            'editDecisionId' => null,
            'editorId' => 1,
            'decision' => SUBMISSION_EDITOR_DECISION_ACCEPT,
            'dateDecided' => date(Core::getCurrentDate())
        ]);

        $reportService = new DataverseReportService();
        $acceptedSubmissions = $reportService->countSubmissions([
            'contextIds' => [$contextId],
            'decisions' => [SUBMISSION_EDITOR_DECISION_ACCEPT],
        ]);
        $declinedSubmissions = $reportService->countSubmissions([
            'contextIds' => [$contextId],
            'decisions' => [SUBMISSION_EDITOR_DECISION_DECLINE],
        ]);

        $this->assertEquals(1, $acceptedSubmissions);
        $this->assertEquals(0, $declinedSubmissions);
    }

    public function testCountSubmissionsWithDataset(): void
    {
        $contextId = $this->createTestContext();

        $submission = $this->createTestSubmission([
            'contextId' => $contextId,
            'submissionProgress' => SUBMISSION_PROGRESS_COMPLETE,
        ]);

        $studyDAO = new DataverseStudyDAO();
        $study = $studyDAO->newDataObject();
        $study->setAllData([
            'submissionId' => $submission->getId(),
            'persistentId' => 'testId',
            'persistentUri' => 'testUri',
            'editUri' => 'testEditUri',
            'editMediaUri' => 'testEditMediaUri',
            'statementUri' => 'testStatementUri',
        ]);
        $studyDAO->insertStudy($study);

        DAORegistry::getDAO('EditDecisionDAO')->updateEditorDecision($submission->getId(), [
            'editDecisionId' => null,
            'editorId' => 1,
            'decision' => SUBMISSION_EDITOR_DECISION_DECLINE,
            'dateDecided' => date(Core::getCurrentDate())
        ]);
        $submission->setStatus(STATUS_DECLINED);
        DAORegistry::getDAO('SubmissionDAO')->updateObject($submission);

        $reportService = new DataverseReportService();
        $this->assertEquals(1, $reportService->countSubmissionsWithDataset([
            'contextIds' => [$contextId],
            'decisions' => [SUBMISSION_EDITOR_DECISION_DECLINE],
        ]));
    }
}
