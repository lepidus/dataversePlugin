<?php

use PKP\tests\DatabaseTestCase;
use PKP\plugins\Hook;
use PKP\core\PKPRouter;
use APP\core\Application;
use APP\submission\Submission;
use APP\publication\Publication;
use APP\template\TemplateManager;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\classes\dispatchers\DataStatementDispatcher;
use APP\plugins\generic\dataverse\classes\services\DataStatementService;
use APP\plugins\generic\dataverse\DataversePlugin;

class DataStatementDispatcherTest extends DatabaseTestCase
{
    private $submissionId;
    private $dispatcher;
    private $request;
    private $originalRouter;

    protected function setUp(): void
    {
        parent::setUp();
        $context = DAORegistry::getDAO('JournalDAO')->getById(1);
        $this->request = Application::get()->getRequest();
        $this->originalRouter = $this->request->getRouter();
        $router = Mockery::mock(PKPRouter::class)->shouldIgnoreMissing();
        $router->shouldReceive('getContext')->andReturn($context);
        $this->request->setRouter($router);

        $plugin = new DataversePlugin();
        $plugin->register('generic', 'plugins/generic/dataverse', $context->getId());
        $this->dispatcher = new DataStatementDispatcher($plugin);
    }

    protected function tearDown(): void
    {
        $this->request->setRouter($this->originalRouter);
        parent::tearDown();
        $submission = Repo::submission()->get($this->submissionId);
        Repo::submission()->delete($submission);
    }

    private function createTestPublication(array $data): int
    {
        $contextId = 1;
        $context = DAORegistry::getDAO('JournalDAO')->getById($contextId);

        $submission = new Submission();
        $submission->setData('contextId', $contextId);
        $publication = new Publication();
        $publication->setAllData($data);

        $this->submissionId = Repo::submission()->add($submission, $publication, $context);
        $submission = Repo::submission()->get($this->submissionId);

        return $submission->getData('currentPublicationId');
    }

    private function renderDataStatement(Publication $publication): string
    {
        $templateMgr = TemplateManager::getManager(Application::get()->getRequest());
        $templateMgr->assign('publication', $publication);
        $output = '';
        $params = [null, $templateMgr, &$output];

        $this->dispatcher->viewDataStatement('Templates::Article::Details', $params);

        return $output;
    }

    public function testDataStatementPropsInPublicationSchema(): void
    {
        $locale = 'en';
        $publicationData = [
            'dataStatementTypes' => [2, 3, 5],
            'dataStatementUrls' => ['https://example.com', 'https://link.to.data'],
            'dataStatementReason' => [$locale => 'Has sensitive data']
        ];

        $publicationId = $this->createTestPublication($publicationData);
        $insertedPublication = Repo::publication()->get($publicationId);

        $this->assertEquals($publicationData['dataStatementTypes'], $insertedPublication->getData('dataStatementTypes'));
        $this->assertEquals($publicationData['dataStatementUrls'], $insertedPublication->getData('dataStatementUrls'));
        $this->assertEquals($publicationData['dataStatementReason'][$locale], $insertedPublication->getData('dataStatementReason', $locale));
    }

    public function testPublicDataStatementRendersRepositoryUrlsAndUnavailableReason(): void
    {
        $locale = 'en';
        $publicationId = $this->createTestPublication([
            'dataStatementTypes' => [
                DataStatementService::DATA_STATEMENT_TYPE_REPO_AVAILABLE,
                DataStatementService::DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED,
                DataStatementService::DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE,
            ],
            'dataStatementUrls' => ['https://example.test/research-data'],
            'dataStatementReason' => [$locale => 'Contains sensitive data'],
        ]);
        $publication = Repo::publication()->get($publicationId);

        $output = $this->renderDataStatement($publication);

        $this->assertStringContainsString('class="item dataStatement"', $output);
        $this->assertStringContainsString('https://example.test/research-data', $output);
        $this->assertStringContainsString('Contains sensitive data', $output);
    }

    public function testPublicDataStatementIsHiddenWhenOnlySubmittedToDataverse(): void
    {
        $publicationId = $this->createTestPublication([
            'dataStatementTypes' => [DataStatementService::DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED],
        ]);
        $publication = Repo::publication()->get($publicationId);

        $output = $this->renderDataStatement($publication);

        $this->assertStringNotContainsString('class="item dataStatement"', $output);
    }
}
