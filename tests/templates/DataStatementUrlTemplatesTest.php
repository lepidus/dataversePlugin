<?php

use APP\plugins\generic\dataverse\classes\services\DataStatementService;
use APP\publication\Publication;
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

    private function renderArticlePageDataStatement(array $dataStatementUrls): string
    {
        $publication = new Publication();
        $publication->setData('dataStatementTypes', [DataStatementService::DATA_STATEMENT_TYPE_REPO_AVAILABLE]);
        $publication->setData('dataStatementUrls', $dataStatementUrls);

        $smarty = new Smarty();
        $smarty->setCompileDir($this->compileDir);
        $smarty->registerPlugin('function', 'translate', fn (array $params) => $params['key']);
        $smarty->assign('publication', $publication);
        $smarty->assign('dataStatementConsts', (new DataStatementService())->getConstantsForTemplates());
        $smarty->assign('dataStatementMessages', [
            DataStatementService::DATA_STATEMENT_TYPE_REPO_AVAILABLE => 'Available in a repository',
        ]);

        return $smarty->fetch(dirname(__DIR__, 2) . '/templates/listDataStatement.tpl');
    }

    public function testOnlyHttpUrlsAreRenderedAsLinksOnTheArticlePage(): void
    {
        $html = $this->renderArticlePageDataStatement([
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
