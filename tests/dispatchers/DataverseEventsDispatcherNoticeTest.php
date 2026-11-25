<?php

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.core.PKPRequest');
import('plugins.generic.dataverse.classes.dispatchers.DataverseEventsDispatcher');
import('plugins.generic.dataverse.classes.entities.DatasetFile');

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

    public function editorChoices(): array
    {
        return [
            'question not shown' => [null, false],
            'answered no' => ['0', false],
            'answered yes' => ['1', true],
        ];
    }

    /**
     * @dataProvider editorChoices
     */
    public function testDecisionActsOnTheResearchDataOnlyWhenTheEditorChoseIt(?string $answer, bool $expected): void
    {
        $request = $this->getMockBuilder(PKPRequest::class)->onlyMethods(['getUserVar'])->getMock();
        $request->method('getUserVar')->with('shouldDeleteResearchData')->willReturn($answer);

        $this->assertSame($expected, $this->invoke('editorChose', $request, 'shouldDeleteResearchData'));
    }

    private function createSmarty(): Smarty
    {
        $smarty = new Smarty();
        $smarty->setCompileDir($this->compileDir);
        $smarty->registerPlugin('block', 'fbvFormSection', function (array $params, $content) {
            return $content;
        });
        $smarty->registerPlugin('function', 'translate', function (array $params) {
            return $params['key'] . ($params['persistentUri'] ?? '');
        });
        $smarty->registerPlugin('function', 'fbvElement', function (array $params) {
            return $params['type'] === 'checkbox' ? $params['label'] : '';
        });

        return $smarty;
    }

    private function fetchTemplate(Smarty $smarty, string $template): string
    {
        return $smarty->fetch(dirname(__DIR__, 2) . '/templates/' . $template);
    }

    public function testDataverseErrorIsEscapedInTheDataFilesSelection(): void
    {
        $smarty = $this->createSmarty();
        $smarty->assign('datasetFiles', []);
        $smarty->assign('dataverseError', 'Dataverse Error: ' . self::MALICIOUS_TEXT);

        $this->assertEscaped($this->fetchTemplate($smarty, 'selectDataFilesForReview.tpl'));
    }

    public function testDataFileNamesAreEscapedInTheDataFilesSelection(): void
    {
        $file = new DatasetFile();
        $file->setId(1);
        $file->setFileName(self::MALICIOUS_TEXT);
        $smarty = $this->createSmarty();
        $smarty->assign('datasetFiles', [$file]);

        $this->assertEscaped($this->fetchTemplate($smarty, 'selectDataFilesForReview.tpl'));
    }

    public function testPersistentUriIsEscapedInTheDeleteNotice(): void
    {
        $smarty = $this->createSmarty();
        $smarty->assign('persistenUri', 'https://doi.org/10.5072/"><img src=x onerror=alert(1)>');

        $this->assertEscaped($this->fetchTemplate($smarty, 'editorActionDelete.tpl'));
    }
}
