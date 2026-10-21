<?php

use APP\core\Application;
use APP\core\Request as PKPRequest;
use APP\core\Services;
use APP\plugins\generic\dataverse\classes\dispatchers\DataStatementDispatcher;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\classes\services\DataStatementService;
use APP\plugins\generic\dataverse\DataversePlugin;
use APP\publication\Publication;
use APP\submission\Submission;
use PKP\core\Registry;
use PKP\tests\DatabaseTestCase;

class DataStatementValidationTest extends DatabaseTestCase
{
    private const REPOSITORY_URL = 'https://demo.dataverse.org/dataset.xhtml?persistentId=doi:10.5072/FK2/U6AEZM';

    private $context;
    private int $sectionId;

    protected function getMockedRegistryKeys(): array
    {
        return ['hooks', 'request'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        new DataStatementDispatcher(new DataversePlugin());
        Services::get('schema')->get('publication', true);

        $contextDao = Application::getContextDAO();
        $this->context = $contextDao->newDataObject();
        $this->context->setPath('dataStatementValidationTest');
        $this->context->setPrimaryLocale('en');
        $this->context->setData('supportedSubmissionLocales', ['en']);
        $this->context->setId($contextDao->insertObject($this->context));
        $section = Repo::section()->newDataObject([
            'contextId' => $this->context->getId(),
            'title' => ['en' => 'Articles'],
            'abbrev' => ['en' => 'ART'],
        ]);
        $this->sectionId = Repo::section()->add($section);

        $request = $this->createMock(PKPRequest::class);
        $request->method('getUser')->willReturn(null);
        $request->method('getContext')->willReturn($this->context);
        Registry::set('request', $request);
    }

    protected function tearDown(): void
    {
        Application::getContextDAO()->deleteObject($this->context);
        parent::tearDown();
        Services::get('schema')->get('publication', true);
    }

    private function validatePublicationEdit(
        array $props,
        string $submissionProgress = '',
        array $dataStatement = [
            'dataStatementTypes' => [DataStatementService::DATA_STATEMENT_TYPE_REPO_AVAILABLE],
            'dataStatementUrls' => [self::REPOSITORY_URL],
        ]
    ): array {
        $submission = new Submission();
        $submission->setAllData([
            'contextId' => $this->context->getId(),
            'locale' => 'en',
            'submissionProgress' => $submissionProgress,
        ]);
        $publication = new Publication();
        $publication->setAllData(array_merge(['sectionId' => $this->sectionId], $dataStatement));
        $submissionId = Repo::submission()->add($submission, $publication, $this->context);
        $submission = Repo::submission()->get($submissionId);

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
}
