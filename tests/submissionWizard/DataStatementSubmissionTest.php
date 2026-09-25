<?php

use APP\facades\Repo;
use APP\plugins\generic\dataverse\classes\services\DataStatementService;
use APP\plugins\generic\dataverse\tests\helpers\DataverseIntegrationFixture;
use PKP\tests\DatabaseTestCase;

class DataStatementSubmissionTest extends DatabaseTestCase
{
    use DataverseIntegrationFixture;

    private const REPOSITORY_URL = 'https://demo.dataverse.org/dataset.xhtml?persistentId=doi:10.5072/FK2/U6AEZM';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFixture();
        $this->configureWithPlaceholderDataverse();
        $this->registerPlugin();
    }

    protected function tearDown(): void
    {
        $this->tearDownFixture();
        parent::tearDown();
        $this->restoreCoreSchemas();
    }

    private function validateSubmission(array $publicationData): array
    {
        $submission = $this->createSubmission($publicationData);

        return Repo::submission()->validateSubmit($submission, $this->context);
    }

    public function testDataStatementIsRequired(): void
    {
        $errors = $this->validateSubmission([]);

        $this->assertEquals([__('plugins.generic.dataverse.dataStatement.required')], $errors['dataStatement']);
    }

    public function testRepositoryUrlsAreRequiredWhenDataIsInRepositories(): void
    {
        $errors = $this->validateSubmission([
            'dataStatementTypes' => [DataStatementService::DATA_STATEMENT_TYPE_REPO_AVAILABLE],
        ]);

        $this->assertEquals(
            [__('plugins.generic.dataverse.dataStatement.repoAvailable.urls.required')],
            $errors['dataStatementUrls']
        );
        $this->assertArrayNotHasKey('dataStatementReason', $errors);
    }

    public function testRepositoryUrlsMustBeUrls(): void
    {
        $errors = $this->validateSubmission([
            'dataStatementTypes' => [DataStatementService::DATA_STATEMENT_TYPE_REPO_AVAILABLE],
            'dataStatementUrls' => ['Example text'],
        ]);

        $this->assertEquals(
            [__('plugins.generic.dataverse.dataStatement.repoAvailable.urls.urlFormat')],
            $errors['dataStatementUrls']
        );
    }

    public function testReasonIsRequiredWhenDataIsPubliclyUnavailable(): void
    {
        $errors = $this->validateSubmission([
            'dataStatementTypes' => [DataStatementService::DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE],
        ]);

        $this->assertEquals(
            [__('plugins.generic.dataverse.dataStatement.publiclyUnavailable.reason.required')],
            $errors['dataStatementReason']
        );
    }

    public function testCompleteDataStatementIsAccepted(): void
    {
        $errors = $this->validateSubmission([
            'dataStatementTypes' => [
                DataStatementService::DATA_STATEMENT_TYPE_REPO_AVAILABLE,
                DataStatementService::DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE,
            ],
            'dataStatementUrls' => [self::REPOSITORY_URL],
            'dataStatementReason' => ['en' => 'Has sensitive data'],
        ]);

        $this->assertArrayNotHasKey('dataStatement', $errors);
        $this->assertArrayNotHasKey('dataStatementUrls', $errors);
        $this->assertArrayNotHasKey('dataStatementReason', $errors);
    }

    public function testDeselectedStatementTypesDiscardTheirDetails(): void
    {
        $submission = $this->createSubmission([
            'dataStatementTypes' => [
                DataStatementService::DATA_STATEMENT_TYPE_REPO_AVAILABLE,
                DataStatementService::DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE,
            ],
            'dataStatementUrls' => [self::REPOSITORY_URL],
            'dataStatementReason' => ['en' => 'Has sensitive data'],
        ]);

        Repo::publication()->edit($submission->getCurrentPublication(), [
            'dataStatementTypes' => [DataStatementService::DATA_STATEMENT_TYPE_IN_MANUSCRIPT],
        ]);

        $publication = Repo::publication()->get($submission->getData('currentPublicationId'));
        $this->assertEquals([DataStatementService::DATA_STATEMENT_TYPE_IN_MANUSCRIPT], $publication->getData('dataStatementTypes'));
        $this->assertNull($publication->getData('dataStatementUrls'));
        $this->assertNull($publication->getData('dataStatementReason', 'en'));
    }
}
