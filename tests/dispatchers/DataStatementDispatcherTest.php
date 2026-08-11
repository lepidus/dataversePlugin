<?php

use PKP\tests\DatabaseTestCase;
use PKP\plugins\Hook;
use APP\submission\Submission;
use APP\publication\Publication;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\classes\dispatchers\DataStatementDispatcher;
use APP\plugins\generic\dataverse\tests\helpers\CreatesTestContext;
use APP\plugins\generic\dataverse\DataversePlugin;

class DataStatementDispatcherTest extends DatabaseTestCase
{
    use CreatesTestContext;

    private $context;
    private $submissionId;

    protected function setUp(): void
    {
        parent::setUp();
        $plugin = new DataversePlugin();
        $dispatcher = new DataStatementDispatcher($plugin);
        $this->context = $this->createTestContext();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $submission = Repo::submission()->get($this->submissionId);
        Repo::submission()->delete($submission);
        $this->deleteTestContext($this->context);
    }

    private function createTestPublication(array $data): int
    {
        $submission = new Submission();
        $submission->setData('contextId', $this->context->getId());
        $publication = new Publication();
        $publication->setAllData($data);

        $this->submissionId = Repo::submission()->add($submission, $publication, $this->context);
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
