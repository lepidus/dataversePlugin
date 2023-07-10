<?php

import('lib.pkp.tests.DatabaseTestCase');
import('plugins.generic.dataverse.classes.services.queryBuilders.DataverseReportQueryBuilder');

class DataverseReportQueryBuilderTest extends DatabaseTestCase
{
    protected function getAffectedTables(): array
    {
        return [
            'publications', 'publication_settings',
            'submissions', 'submission_settings',
            'journals', 'journal_settings'
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

    public function testFilterSubmissionByContext(): void
    {
        $contextId = $this->createTestContext();
        $submission = $this->createTestSubmission([
            'contextId' => $contextId,
            'submissionProgress' => 0,
        ]);

        $query = $this->getQueryBuilder()->filterByContexts($contextId)->getQuery();

        $this->assertEquals($submission->getId(), $query->get()->first()->submission_id);
    }
}
