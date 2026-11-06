<?php

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.core.PKPString');

class AdditionalInstructionsTemplatesTest extends PKPTestCase
{
    private $compileDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->compileDir = sys_get_temp_dir() . '/dataverse-templates-test-' . uniqid();
        mkdir($this->compileDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->compileDir . '/*') ?: []);
        rmdir($this->compileDir);
        parent::tearDown();
    }

    private function renderTemplate(string $template, string $additionalInstructions): string
    {
        $smarty = new Smarty();
        $smarty->setCompileDir($this->compileDir);
        $smarty->registerPlugin('modifier', 'strip_unsafe_html', ['PKPString', 'stripUnsafeHtml']);
        $smarty->registerPlugin('modifier', 'uniqid', 'uniqid');
        $smarty->registerPlugin('function', 'translate', function (array $params) {
            return $params['key'];
        });
        $smarty->registerPlugin('function', 'url', function () {
            return 'https://journal.test/grid';
        });
        $smarty->registerPlugin('function', 'load_url_in_div', function () {
            return '';
        });
        $smarty->assign('dataverseAdditionalInstructions', $additionalInstructions);

        return $smarty->fetch(dirname(__DIR__, 2) . '/templates/' . $template);
    }

    public function templatesWithAdditionalInstructions(): array
    {
        return [
            'research data tab' => ['datasetTab/datasetData.tpl'],
            'research data tab without dataset' => ['datasetTab/noResearchData.tpl'],
            'submission wizard files step' => ['draftDatasetFile.tpl'],
        ];
    }

    /**
     * @dataProvider templatesWithAdditionalInstructions
     */
    public function testAdditionalInstructionsAreRenderedWithoutScriptsOrVueExpressions(string $template): void
    {
        $html = $this->renderTemplate(
            $template,
            '<p>Deposit your data</p><img src="x" onerror="alert(1)"><script>alert(2)</script><p>{{ constructor }}</p>'
        );

        $this->assertStringContainsString('<p>Deposit your data</p>', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertMatchesRegularExpression('/<div v-pre>(?:(?!<\/div>).)*\{\{ constructor \}\}/s', $html);
    }
}
