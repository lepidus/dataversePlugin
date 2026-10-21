<?php

use APP\plugins\generic\dataverse\classes\dispatchers\DatasetReviewDispatcher;
use APP\plugins\generic\dataverse\classes\services\DataStatementService;
use PKP\facades\Locale;
use PKP\tests\PKPTestCase;

/**
 * @runTestsInSeparateProcesses
 *
 * @preserveGlobalState disabled
 */
class DatasetReviewDispatcherTest extends PKPTestCase
{
    private const DATAVERSE_SUBMITTED = 3;

    public function testDataverseNameIsEscapedInTheReviewerDataStatement(): void
    {
        Locale::registerPath(dirname(__DIR__, 2) . '/locale');
        Mockery::getConfiguration()->setConstantsMap([
            DataStatementService::class => ['DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED' => self::DATAVERSE_SUBMITTED],
        ]);
        $service = Mockery::mock('overload:' . DataStatementService::class);
        $service->shouldReceive('getDataStatementTypes')->andReturn([]);
        $service->shouldReceive('getDataverseName')->andReturn('<img src=x onerror=alert(1)>');

        $dispatcher = (new ReflectionClass(DatasetReviewDispatcher::class))->newInstanceWithoutConstructor();
        $getDataStatementTypes = new ReflectionMethod($dispatcher, 'getDataStatementTypes');
        $getDataStatementTypes->setAccessible(true);

        $types = $getDataStatementTypes->invoke($dispatcher);

        $label = $types[self::DATAVERSE_SUBMITTED];
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $label);
        $this->assertStringNotContainsString('<img', $label);
    }
}
