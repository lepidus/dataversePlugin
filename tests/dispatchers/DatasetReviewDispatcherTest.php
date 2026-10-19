<?php

use APP\plugins\generic\dataverse\classes\dispatchers\DatasetReviewDispatcher;
use APP\plugins\generic\dataverse\classes\services\DataStatementService;
use PKP\facades\Locale;
use PKP\tests\PKPTestCase;

class DatasetReviewDispatcherTest extends PKPTestCase
{
    public function testDataverseNameIsEscapedInTheReviewerDataStatement(): void
    {
        Locale::registerPath(dirname(__DIR__, 2) . '/locale');
        $service = $this->createMock(DataStatementService::class);
        $service->method('getDataStatementTypes')->willReturn([]);
        $service->method('getDataverseName')->willReturn('<img src=x onerror=alert(1)>');

        $dispatcher = (new ReflectionClass(DatasetReviewDispatcher::class))->newInstanceWithoutConstructor();
        $getDataStatementTypes = new ReflectionMethod($dispatcher, 'getDataStatementTypes');

        $types = $getDataStatementTypes->invoke($dispatcher, $service);

        $label = $types[DataStatementService::DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED];
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $label);
        $this->assertStringNotContainsString('<img', $label);
    }
}
