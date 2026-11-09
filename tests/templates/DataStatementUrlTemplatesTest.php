<?php

use APP\plugins\generic\dataverse\classes\services\DataStatementService;
use APP\publication\Publication;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\tests\PKPTestCase;

class DataStatementUrlTemplatesTest extends PKPTestCase
{
    private const REPOSITORY_URL = 'https://demo.dataverse.org/dataset.xhtml?persistentId=doi:10.5072/FK2/U6AEZM';

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

    private function renderTemplate(string $template, array $dataStatementUrls): string
    {
        $publication = new Publication();
        $publication->setData('dataStatementTypes', [DataStatementService::DATA_STATEMENT_TYPE_REPO_AVAILABLE]);
        $publication->setData('dataStatementUrls', $dataStatementUrls);
        $typeLabels = [DataStatementService::DATA_STATEMENT_TYPE_REPO_AVAILABLE => 'Available in a repository'];

        $smarty = new Smarty();
        $smarty->setCompileDir($this->compileDir);
        $smarty->registerPlugin('function', 'translate', fn (array $params) => $params['key']);
        $smarty->registerPlugin('block', 'fbvFormSection', fn (array $params, $content) => $content);
        $smarty->registerPlugin('modifier', 'uniqid', 'uniqid');
        $smarty->registerPlugin('function', 'url', fn () => 'https://journal.test/grid');
        $smarty->registerPlugin('function', 'load_url_in_div', fn () => '');
        $smarty->assign('publication', $publication);
        $smarty->assign('dataStatementConsts', (new DataStatementService())->getConstantsForTemplates());
        $smarty->assign('dataStatementMessages', $typeLabels);
        $smarty->assign('allDataStatementTypes', $typeLabels);
        $smarty->assign('restrictReviewerFileAccess', true);

        return $smarty->fetch(dirname(__DIR__, 2) . '/templates/' . $template);
    }

    public static function templatesWithDataStatementUrls(): array
    {
        return [
            'article page' => ['listDataStatement.tpl'],
            'reviewer form' => ['datasetReview.tpl'],
        ];
    }

    #[DataProvider('templatesWithDataStatementUrls')]
    public function testOnlyHttpUrlsAreRenderedAsLinks(string $template): void
    {
        $html = $this->renderTemplate($template, [
            self::REPOSITORY_URL,
            'HTTP://DEMO.DATAVERSE.ORG/DATASET',
            'javascript:alert(document.domain)',
            ' javascript:alert(1)',
            'data:text/html,<script>alert(2)</script>',
            'demo.dataverse.org/dataset',
        ]);

        $this->assertStringContainsString('<a href="' . htmlspecialchars(self::REPOSITORY_URL) . '"', $html);
        $this->assertStringContainsString('<a href="HTTP://DEMO.DATAVERSE.ORG/DATASET"', $html);
        $this->assertStringContainsString('javascript:alert(document.domain)', $html);
        $this->assertStringContainsString('data:text/html,&lt;script&gt;', $html);
        $this->assertSame(2, substr_count($html, '<a href='));
        $this->assertStringNotContainsString('<script', $html);
    }
}
