<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.dispatchers.DataverseEventsDispatcher');

class DataverseEventsDispatcherNoticeTest extends PKPTestCase
{
    private const MALICIOUS_TEXT = '<img src=x onerror=alert(1)>';

    private $dispatcher;
    private $compileDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = (new ReflectionClass(DataverseEventsDispatcher::class))->newInstanceWithoutConstructor();
        $this->compileDir = sys_get_temp_dir() . '/dataverse-templates-test-' . uniqid();
        mkdir($this->compileDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->compileDir . '/*') ?: []);
        rmdir($this->compileDir);
        parent::tearDown();
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

    public function testDataverseValuesAreEscapedBeforeEnteringTheNotices(): void
    {
        $params = $this->invoke('escapeHtmlParams', [
            'serverName' => self::MALICIOUS_TEXT,
            'persistentUri' => 'https://doi.org/10.5072/"><img src=x onerror=alert(1)>',
        ]);

        $this->assertSame('&lt;img src=x onerror=alert(1)&gt;', $params['serverName']);
        $this->assertSame('https://doi.org/10.5072/&quot;&gt;&lt;img src=x onerror=alert(1)&gt;', $params['persistentUri']);
    }

    public function testDataverseErrorIsEscapedInTheDataFilesSelection(): void
    {
        $smarty = new Smarty();
        $smarty->setCompileDir($this->compileDir);
        $smarty->registerPlugin('block', 'fbvFormSection', function (array $params, $content) {
            return $content;
        });
        $smarty->registerPlugin('function', 'translate', function (array $params) {
            return $params['key'];
        });
        $smarty->registerPlugin('function', 'fbvElement', function () {
            return '';
        });
        $smarty->assign('datasetFiles', []);
        $smarty->assign('dataverseError', 'Dataverse Error: ' . self::MALICIOUS_TEXT);

        $this->assertEscaped($smarty->fetch(dirname(__DIR__, 2) . '/templates/selectDataFilesForReview.tpl'));
    }
}
