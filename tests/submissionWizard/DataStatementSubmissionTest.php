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

    private function validatePublicationEdit(
        array $props,
        string $submissionProgress = '',
        array $dataStatement = [
            'dataStatementTypes' => [DataStatementService::DATA_STATEMENT_TYPE_REPO_AVAILABLE],
            'dataStatementUrls' => [self::REPOSITORY_URL],
        ]
    ): array {
        $submission = $this->createSubmission($dataStatement, ['submissionProgress' => $submissionProgress]);
        $this->context->setData('supportedSubmissionMetadataLocales', ['en']);

        return Repo::publication()->validate($submission->getCurrentPublication(), $props, $submission, $this->context);
    }

    public function testRepositoryUrlsMustBeUrlsWhenThePublicationIsEdited(): void
    {
        $errors = $this->validatePublicationEdit([
            'dataStatementUrls' => [self::REPOSITORY_URL, 'javascript:alert(document.cookie)'],
        ]);

        $this->assertArrayHasKey('dataStatementUrls', $errors);
        $this->assertEquals(
            [__('plugins.generic.dataverse.dataStatement.repoAvailable.urls.urlFormat')],
            $errors['dataStatementUrls']
        );
    }

    public function testRepositoryUrlsAreRequiredWhenThePublicationIsEdited(): void
    {
        $errors = $this->validatePublicationEdit(['dataStatementUrls' => []]);

        $this->assertArrayHasKey('dataStatementUrls', $errors);
        $this->assertEquals(
            [__('plugins.generic.dataverse.dataStatement.repoAvailable.urls.required')],
            $errors['dataStatementUrls']
        );
    }

    public function testRepositoryTypeCannotBeSelectedWithoutUrls(): void
    {
        $errors = $this->validatePublicationEdit(
            ['dataStatementTypes' => [DataStatementService::DATA_STATEMENT_TYPE_REPO_AVAILABLE]],
            '',
            ['dataStatementTypes' => [DataStatementService::DATA_STATEMENT_TYPE_ON_DEMAND]]
        );

        $this->assertArrayHasKey('dataStatementUrls', $errors);
    }

    public function testRepositoryUrlThatIsNotTextIsReportedAsInvalid(): void
    {
        $errors = $this->validatePublicationEdit(['dataStatementUrls' => [['javascript:alert(1)']]]);

        $this->assertArrayHasKey('dataStatementUrls', $errors);
    }

    public function testStaleRepositoryUrlsDoNotBlockChangingTheStatementType(): void
    {
        $errors = $this->validatePublicationEdit([
            'dataStatementTypes' => [DataStatementService::DATA_STATEMENT_TYPE_ON_DEMAND],
            'dataStatementUrls' => ['Example text'],
        ]);

        $this->assertArrayNotHasKey('dataStatementUrls', $errors);
    }

    public function testRepositoryUrlsBeingTypedInTheWizardAreOnlyValidatedOnSubmit(): void
    {
        $errors = $this->validatePublicationEdit(['dataStatementUrls' => ['https://demo']], 'details');

        $this->assertArrayNotHasKey('dataStatementUrls', $errors);
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

    public function testSubmissionStartedWithoutDataStatementIsSubmittedOnceItIsInformed(): void
    {
        $submission = $this->createSubmission();
        $this->assertArrayHasKey('dataStatement', Repo::submission()->validateSubmit($submission, $this->context));

        Repo::publication()->edit($submission->getCurrentPublication(), [
            'dataStatementTypes' => [DataStatementService::DATA_STATEMENT_TYPE_IN_MANUSCRIPT],
        ]);
        $submission = Repo::submission()->get($submission->getId());
        $this->assertArrayNotHasKey('dataStatement', Repo::submission()->validateSubmit($submission, $this->context));

        Repo::submission()->submit($submission, $this->context);
        $this->assertNotNull(Repo::submission()->get($submission->getId())->getData('dateSubmitted'));
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
