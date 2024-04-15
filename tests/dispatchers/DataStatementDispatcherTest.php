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
    private $submissionId;

    protected function setUp(): void
    {
        parent::setUp();
        $plugin = new DataversePlugin();
        $dispatcher = new DataStatementDispatcher($plugin);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $submission = Repo::submission()->get($this->submissionId);
        Repo::submission()->delete($submission);
    }

    private function createTestPublication(array $data): int
    {
        $contextId = 1;
        $context = DAORegistry::getDAO('JournalDAO')->getById($contextId);

        $submission = new Submission();
        $submission->setData('contextId', $contextId);
        $publication = new Publication();
        $publication->setAllData($data);

        $this->submissionId = Repo::submission()->add($submission, $publication, $context);
        $submission = Repo::submission()->get($this->submissionId);

        return $submission->getData('currentPublicationId');
    }

    public function testDataStatementPropsInPublicationSchema(): void
    {
        $locale = 'en';
        $publicationData = [
            'dataStatementTypes' => [2, 3, 5],
            'dataStatementUrls' => ['https://example.com', 'https://link.to.data'],
            'dataStatementReason' => [$locale => 'Has sensitive data']
        ];

        $publicationId = $this->createTestPublication($publicationData);
        $insertedPublication = Repo::publication()->get($publicationId);

        $this->assertEquals($publicationData['dataStatementTypes'], $insertedPublication->getData('dataStatementTypes'));
        $this->assertEquals($publicationData['dataStatementUrls'], $insertedPublication->getData('dataStatementUrls'));
        $this->assertEquals($publicationData['dataStatementReason'][$locale], $insertedPublication->getData('dataStatementReason', $locale));
    }
}
