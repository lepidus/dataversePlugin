<?php

use PKP\tests\DatabaseTestCase;
use PKP\plugins\Hook;
use APP\submission\Submission;
use APP\publication\Publication;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\classes\dispatchers\DataStatementDispatcher;
use APP\plugins\generic\dataverse\DataversePlugin;

class DataStatementDispatcherTest extends DatabaseTestCase
{
    protected function getAffectedTables(): array
    {
        return ['publications','publication_settings'];
    }

    private function createTestPublication(array $data): int
    {
        $contextId = 1;
        $context = DAORegistry::getDAO('JournalDAO')->getById($contextId);

        $submission = new Submission();
        $submission->setData('contextId', $contextId);
        $publication = new Publication();
        $publication->setAllData($data);

        $submissionId = Repo::submission()->add($submission, $publication, $context);
        $submission = Repo::submission()->get($submissionId);

        return $submission->getData('currentPublicationId');
    }

    public function testDataStatementPropsInPublicationSchema(): void
    {
        $plugin = new DataversePlugin();
        $dispatcher = new DataStatementDispatcher($plugin);
        $publicationData = [
            'dataStatementTypes' => [2, 3, 5],
            'dataStatementUrls' => ['https://example.com', 'https://link.to.data'],
            'dataStatementReason' => 'Has sensitive data'
        ];

        Hook::add('Schema::get::publication', [$dispatcher, 'addDataStatementToPublicationSchema']);

        $publicationId = $this->createTestPublication($publicationData);
        $insertedPublication = Repo::publication()->get($publicationId);

        $this->assertEquals($dataStatementTypes, $insertedPublication->getData('dataStatementTypes'));
        $this->assertEquals($dataStatementUrls, $insertedPublication->getData('dataStatementUrls'));
        $this->assertEquals($dataStatementReason, $insertedPublication->getData('dataStatementReason'));
    }
}
