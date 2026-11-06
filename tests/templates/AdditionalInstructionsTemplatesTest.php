<?php

use PKP\core\PKPString;
use PKP\tests\PKPTestCase;

class AdditionalInstructionsTemplatesTest extends PKPTestCase
{
    private string $compileDir;

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
        $smarty->registerPlugin('modifier', 'strip_unsafe_html', [PKPString::class, 'stripUnsafeHtml']);
        $smarty->registerPlugin('function', 'translate', fn (array $params) => $params['key']);
        $smarty->assign('dataverseAdditionalInstructions', $additionalInstructions);

        return $smarty->fetch(dirname(__DIR__, 2) . '/templates/' . $template);
    }

    public static function templatesWithAdditionalInstructions(): array
    {
        return [
            'research data tab' => ['datasetTab/datasetData.tpl'],
            'research data tab without dataset' => ['datasetTab/noResearchData.tpl'],
            'submission wizard files step' => ['draftDatasetFiles.tpl'],
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
