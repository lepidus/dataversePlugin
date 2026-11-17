<?php

use APP\plugins\generic\dataverse\classes\dispatchers\DataverseEventsDispatcher;
use PKP\components\forms\FormComponent;
use PKP\facades\Locale;
use PKP\tests\PKPTestCase;

/**
 * @runTestsInSeparateProcesses
 *
 * @preserveGlobalState disabled
 */
class DataverseEventsDispatcherNoticeTest extends PKPTestCase
{
    private const MALICIOUS_TEXT = '<img src=x onerror=alert(1)>';

    private DataverseEventsDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        Locale::registerPath(dirname(__DIR__, 2) . '/locale');
        $this->dispatcher = (new ReflectionClass(DataverseEventsDispatcher::class))->newInstanceWithoutConstructor();
    }

    private function invoke(string $method, ...$args)
    {
        $reflectionMethod = new ReflectionMethod($this->dispatcher, $method);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invoke($this->dispatcher, ...$args);
    }

    private function assertEscaped(string $html): void
    {
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function testDataverseErrorIsEscapedInTheCannotPublishNotice(): void
    {
        $this->assertEscaped($this->invoke('getCannotPublishNotice', self::MALICIOUS_TEXT));
    }

    public function testDataverseNameIsEscapedInThePublishNotice(): void
    {
        $form = new FormComponent('datasetPublish', '', FormComponent::ACTION_EMIT, []);

        $this->invoke('addDatasetPublishFieldsToForm', $form, [
            'persistentUri' => 'https://doi.org/10.5072/FK2/ABC',
            'serverName' => self::MALICIOUS_TEXT,
            'serverUrl' => 'https://demo.dataverse.org',
        ]);

        $this->assertEscaped($form->getField('researchDataNotice')->description);
    }

    public function testPersistentUriIsEscapedInTheDeleteNotice(): void
    {
        $form = $this->invoke('getDatasetDeleteNoticeForm', 'https://doi.org/10.5072/"><img src=x onerror=alert(1)>');

        $this->assertEscaped($form->getField('researchDataNotice')->description);
    }
}
