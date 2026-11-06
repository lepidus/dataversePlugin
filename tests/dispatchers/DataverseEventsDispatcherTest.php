<?php

use APP\API\v1\submissions\SubmissionHandler;
use APP\core\Request;
use APP\core\Services;
use APP\journal\Journal;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfigurationDAO;
use APP\plugins\generic\dataverse\classes\dispatchers\DataverseEventsDispatcher;
use PKP\cache\CacheManager;
use PKP\core\Registry;
use PKP\db\DAORegistry;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;
use PKP\tests\DatabaseTestCase;

class DataverseEventsDispatcherTest extends DatabaseTestCase
{
    private const CONTEXT_ID = 9092;

    private $previousConfigurationDAO;

    protected function getMockedRegistryKeys(): array
    {
        return ['request', 'hooks'];
    }

    protected function getAffectedTables(): array
    {
        return ['plugin_settings'];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $configurationDAO = new DataverseConfigurationDAO();
        $this->previousConfigurationDAO = DAORegistry::registerDAO('DataverseConfigurationDAO', $configurationDAO);
        $configuration = $configurationDAO->newDataObject();
        $configuration->setDataverseUrl('https://dataverse.invalid/dataverse/example');
        $configuration->setAPIToken('apiToken');
        $configuration->setTermsOfUse(['en' => 'https://dataverse.invalid/terms']);
        $configurationDAO->insert(self::CONTEXT_ID, $configuration);

        $this->getRequiredMetadataCache()->setEntireCache([]);

        $context = $this->createMock(Journal::class);
        $context->method('getId')->willReturn(self::CONTEXT_ID);
        $request = $this->createMock(Request::class);
        $request->method('getContext')->willReturn($context);
        Registry::set('request', $request);

        $dispatcher = (new ReflectionClass(DataverseEventsDispatcher::class))->newInstanceWithoutConstructor();
        Hook::add('Schema::get::submission', [$dispatcher, 'modifySubmissionSchema']);
        Services::get('schema')->get(PKPSchemaService::SCHEMA_SUBMISSION, true);
    }

    protected function tearDown(): void
    {
        $this->getRequiredMetadataCache()->flush();
        DAORegistry::registerDAO('DataverseConfigurationDAO', $this->previousConfigurationDAO);
        parent::tearDown();
        Services::get('schema')->get(PKPSchemaService::SCHEMA_SUBMISSION, true);
    }

    private function getRequiredMetadataCache()
    {
        return CacheManager::getManager()->getFileCache(
            self::CONTEXT_ID,
            'dataverse_required_metadata',
            fn () => null
        );
    }

    public function testFilesSelectedForReviewCannotBeWrittenThroughTheSubmissionsApi(): void
    {
        $handler = (new ReflectionClass(SubmissionHandler::class))->newInstanceWithoutConstructor();
        $getWriteDisabledErrors = new ReflectionMethod($handler, 'getWriteDisabledErrors');
        $getWriteDisabledErrors->setAccessible(true);

        $errors = $getWriteDisabledErrors->invoke(
            $handler,
            PKPSchemaService::SCHEMA_SUBMISSION,
            ['selectedDataFilesForReview' => [41]]
        );

        $this->assertArrayHasKey('selectedDataFilesForReview', $errors);
    }
}
