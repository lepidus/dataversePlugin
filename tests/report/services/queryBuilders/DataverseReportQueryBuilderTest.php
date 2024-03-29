<?php

import('lib.pkp.tests.DatabaseTestCase');
import('plugins.generic.dataverse.report.services.queryBuilders.DataverseReportQueryBuilder');

class DataverseReportQueryBuilderTest extends DatabaseTestCase
{
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

    private function getQueryBuilder(): DataverseReportQueryBuilder
    {
        return new DataverseReportQueryBuilder();
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

    public function testFilterSubmissionByContexts(): void
    {
        $contextId = $this->createTestContext();
        $submission = $this->createTestSubmission([
            'contextId' => $contextId,
            'submissionProgress' => SUBMISSION_PROGRESS_COMPLETE,
        ]);

        $query = $this->getQueryBuilder()
            ->filterByContexts($contextId)
            ->getQuery();

        $this->assertEquals(
            $submission->getId(),
            $query->get()->first()->submission_id
        );
    }

    public function testFilterSubmissionByDecisions(): void
    {
        $contextId = $this->createTestContext();

        $acceptedSubmission = $this->createTestSubmission([
            'contextId' => $contextId,
            'submissionProgress' => SUBMISSION_PROGRESS_COMPLETE,
        ]);

        $declinedSubmission = $this->createTestSubmission([
            'contextId' => $contextId,
            'submissionProgress' => SUBMISSION_PROGRESS_COMPLETE,
        ]);

        DAORegistry::getDAO('EditDecisionDAO')->updateEditorDecision($acceptedSubmission->getId(), [
            'editDecisionId' => null,
            'editorId' => 1,
            'decision' => SUBMISSION_EDITOR_DECISION_ACCEPT,
            'dateDecided' => date(Core::getCurrentDate())
        ]);

        DAORegistry::getDAO('EditDecisionDAO')->updateEditorDecision($declinedSubmission->getId(), [
            'editDecisionId' => null,
            'editorId' => 1,
            'decision' => SUBMISSION_EDITOR_DECISION_DECLINE,
            'dateDecided' => date(Core::getCurrentDate())
        ]);

        $declinedSubmission->setStatus(STATUS_DECLINED);
        DAORegistry::getDAO('SubmissionDAO')->updateObject($declinedSubmission);

        $query = $this->getQueryBuilder()
            ->filterByContexts($contextId);

        $acceptedQuery = $query->filterByDecisions([SUBMISSION_EDITOR_DECISION_ACCEPT])
            ->getQuery();

        $declinedQuery = $query->filterByDecisions([SUBMISSION_EDITOR_DECISION_DECLINE])
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
        $contextId = $this->createTestContext();

        $submission = $this->createTestSubmission([
            'contextId' => $contextId,
            'submissionProgress' => SUBMISSION_PROGRESS_COMPLETE,
        ]);

        $datasetSubmission = $this->createTestSubmission([
            'contextId' => $contextId,
            'submissionProgress' => SUBMISSION_PROGRESS_COMPLETE,
        ]);

        $studyDAO = new DataverseStudyDAO();
        $study = $studyDAO->newDataObject();
        $study->setAllData([
            'submissionId' => $datasetSubmission->getId(),
            'persistentId' => 'testId',
            'persistentUri' => 'testUri',
            'editUri' => 'testEditUri',
            'editMediaUri' => 'testEditMediaUri',
            'statementUri' => 'testStatementUri',
        ]);
        $studyDAO->insertStudy($study);

        $query = $this->getQueryBuilder()
            ->filterByContexts($contextId)
            ->getWithDataset();

        $this->assertEquals(
            $datasetSubmission->getId(),
            $query->get()->first()->submission_id
        );
    }

    public function testCountDatasetsWithDepositError(): void
    {
        $contextId = $this->createTestContext();

        $submission = $this->createTestSubmission([
            'contextId' => $contextId,
            'submissionProgress' => SUBMISSION_PROGRESS_COMPLETE,
        ]);

        import('classes.log.SubmissionEventLogEntry');

        $submissionEventLogDao = DAORegistry::getDAO('SubmissionEventLogDAO');
        $depositErrorEntry = $submissionEventLogDao->newDataObject();
        $depositErrorEntry->setDateLogged(Core::getCurrentDate());
        $depositErrorEntry->setUserId(rand());
        $depositErrorEntry->setSubmissionId($submission->getId());
        $depositErrorEntry->setEventType(SUBMISSION_LOG_METADATA_UPDATE);
        $depositErrorEntry->setMessage('plugins.generic.dataverse.error.depositFailed');
        $depositErrorEntry->setParams([]);
        $depositErrorEntry->setIsTranslated(0);
        $submissionEventLogDao->insertObject($depositErrorEntry);

        $submissionEventLogDao = DAORegistry::getDAO('SubmissionEventLogDAO');
        $publishErrorEntry = $submissionEventLogDao->newDataObject();
        $publishErrorEntry->setDateLogged(Core::getCurrentDate());
        $publishErrorEntry->setUserId(rand());
        $publishErrorEntry->setSubmissionId($submission->getId());
        $publishErrorEntry->setEventType(SUBMISSION_LOG_METADATA_UPDATE);
        $publishErrorEntry->setMessage('plugins.generic.dataverse.error.publishFailed');
        $publishErrorEntry->setParams([]);
        $publishErrorEntry->setIsTranslated(0);
        $submissionEventLogDao->insertObject($publishErrorEntry);

        $depositErrorsCount = $this->getQueryBuilder()
            ->filterByContexts($contextId)
            ->countDatasetsWithError(['plugins.generic.dataverse.error.depositFailed']);

        $publishErrorsCount = $this->getQueryBuilder()
            ->filterByContexts($contextId)
            ->countDatasetsWithError(['plugins.generic.dataverse.error.publishFailed']);

        $this->assertEquals(1, $depositErrorsCount);
        $this->assertEquals(1, $publishErrorsCount);
    }
}
