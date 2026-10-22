<?php

use APP\core\Application;
use APP\plugins\generic\dataverse\classes\services\DataStatementService;
use APP\plugins\generic\dataverse\tests\helpers\DataverseIntegrationFixture;
use APP\template\TemplateManager;
use PKP\plugins\Hook;
use PKP\tests\DatabaseTestCase;

class DataStatementPublicPageTest extends DatabaseTestCase
{
    use DataverseIntegrationFixture;

    private const REPOSITORY_URL = 'https://demo.dataverse.org/dataset.xhtml?persistentId=doi:10.5072/FK2/U6AEZM';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFixture();
        $this->configureWithPlaceholderDataverse();
        $this->registerPlugin(Application::get()->getName() === 'ojs2' ? 'article/view' : 'preprint/view');
    }

    protected function tearDown(): void
    {
        $this->tearDownFixture();
        parent::tearDown();
        $this->restoreCoreSchemas();
    }

    private function renderPublicationDetails(array $publicationData): string
    {
        $submission = $this->createSubmission($publicationData);
        $isJournal = Application::get()->getName() === 'ojs2';

        $templateMgr = TemplateManager::getManager(Application::get()->getRequest());
        $templateMgr->assign([
            $isJournal ? 'article' : 'preprint' => $submission,
            'publication' => $submission->getCurrentPublication(),
        ]);

        $output = '';
        Hook::call($isJournal ? 'Templates::Article::Details' : 'Templates::Preprint::Details', [[], $templateMgr, &$output]);

        return $output;
    }

    public function testDataStatementIsListedOnThePublicPage(): void
    {
        $output = $this->renderPublicationDetails([
            'dataStatementTypes' => [
                DataStatementService::DATA_STATEMENT_TYPE_REPO_AVAILABLE,
                DataStatementService::DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE,
                DataStatementService::DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED,
            ],
            'dataStatementUrls' => [self::REPOSITORY_URL],
            'dataStatementReason' => ['en' => 'Has sensitive data'],
        ]);

        $this->assertStringContainsString(__('plugins.generic.dataverse.dataStatement.title'), $output);
        $this->assertStringContainsString(__('plugins.generic.dataverse.dataStatement.repoAvailable'), $output);
        $this->assertStringContainsString('<a href="' . htmlspecialchars(self::REPOSITORY_URL) . '"', $output);
        $this->assertStringContainsString(__('plugins.generic.dataverse.dataStatement.publiclyUnavailable'), $output);
        $this->assertStringContainsString('<li>Has sensitive data</li>', $output);
        $this->assertStringNotContainsString('has been submitted to', $output);
    }

    public function testDataStatementIsNotShownWhenResearchDataIsOnlySubmittedToDataverse(): void
    {
        $output = $this->renderPublicationDetails([
            'dataStatementTypes' => [DataStatementService::DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED],
        ]);

        $this->assertStringNotContainsString(__('plugins.generic.dataverse.dataStatement.title'), $output);
    }
}
