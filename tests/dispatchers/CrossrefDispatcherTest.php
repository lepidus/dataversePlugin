<?php

use PKP\db\DAORegistry;
use PKP\doi\Doi;
use PKP\tests\DatabaseTestCase;
use APP\publication\Publication;
use APP\submission\Submission;
use APP\plugins\generic\dataverse\classes\CrossrefXmlEditor;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfigurationDAO;
use APP\plugins\generic\dataverse\classes\dataverseStudy\DataverseStudy;
use APP\plugins\generic\dataverse\classes\dispatchers\CrossrefDispatcher;
use APP\plugins\generic\dataverse\classes\entities\Dataset;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DatasetActions;
use APP\plugins\generic\dataverse\tests\helpers\CreatesTestContext;
use APP\plugins\generic\dataverse\DataversePlugin;

class CrossrefDispatcherTest extends DatabaseTestCase
{
    use CreatesTestContext;

    private $context;
    private DataversePlugin $plugin;
    private CrossrefDispatcher $dispatcher;
    private ?Submission $submission = null;
    private ?int $doiId = null;
    private string $doi = '10.1234/PublicKnowledge.17';
    private string $persistentId = 'doi:10.5072/FK2/ABCDEF';

    protected function setUp(): void
    {
        parent::setUp();

        DAORegistry::registerDAO('DataverseConfigurationDAO', new DataverseConfigurationDAO());

        $this->context = $this->createTestContext();
        $this->createTestSubmission();
        $this->createDataverseStudy();

        $this->plugin = new DataversePlugin();
        $this->dispatcher = $this->createDispatcher();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Repo::submission()->delete($this->submission);

        $doi = Repo::doi()->get($this->doiId);
        if ($doi) {
            Repo::doi()->delete($doi);
        }

        $this->deleteTestContext($this->context);
    }

    private function createTestSubmission(): void
    {
        $submission = new Submission();
        $submission->setData('contextId', $this->context->getId());

        $submissionId = Repo::submission()->add($submission, new Publication(), $this->context);
        $this->submission = Repo::submission()->get($submissionId);

        $doi = Repo::doi()->newDataObject([
            'contextId' => $this->context->getId(),
            'doi' => $this->doi,
            'status' => Doi::STATUS_REGISTERED,
        ]);
        $this->doiId = Repo::doi()->add($doi);

        $publication = $this->submission->getCurrentPublication();
        $publication->setData('doiId', $this->doiId);
        Repo::publication()->dao->update($publication);
    }

    private function createDataverseStudy(): void
    {
        $study = new DataverseStudy();
        $study->setSubmissionId($this->submission->getId());
        $study->setEditUri('https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit/study/' . $this->persistentId);
        $study->setEditMediaUri('https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit-media/study/' . $this->persistentId);
        $study->setStatementUri('https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/statement/study/' . $this->persistentId);
        $study->setPersistentUri('https://doi.org/10.5072/FK2/ABCDEF');
        $study->setPersistentId($this->persistentId);

        Repo::dataverseStudy()->add($study);
    }

    private function createDispatcher(): CrossrefDispatcher
    {
        $dataset = new Dataset();
        $dataset->setPersistentId($this->persistentId);
        $dataset->setVersionState(Dataset::VERSION_STATE_RELEASED);

        $datasetActions = $this->createMock(DatasetActions::class);
        $datasetActions->method('get')->willReturn($dataset);

        return new class ($this->plugin, $datasetActions) extends CrossrefDispatcher {
            private DatasetActions $datasetActions;

            public function __construct($plugin, DatasetActions $datasetActions)
            {
                $this->datasetActions = $datasetActions;
                parent::__construct($plugin);
            }

            protected function createXmlEditor(DataverseConfiguration $configuration, int $contextId): CrossrefXmlEditor
            {
                return new CrossrefXmlEditor($this->datasetActions);
            }
        };
    }

    private function enablePluginForContext(): void
    {
        $this->plugin->updateSetting($this->context->getId(), 'enabled', true, 'bool');
    }

    private function configurePluginForContext(): void
    {
        $configuration = new DataverseConfiguration();
        $configuration->setDataverseUrl('https://demo.dataverse.org/dataverse/test');
        $configuration->setAPIToken('some-api-token');
        $configuration->setTermsOfUse(['en' => 'Terms of use']);

        DAORegistry::getDAO('DataverseConfigurationDAO')->insert($this->context->getId(), $configuration);
    }

    private function dispatchDepositXml(): DOMDocument
    {
        $depositXml = new DOMDocument();
        $depositXml->load(__DIR__ . '/../fixtures/crossref/article_deposit.xml');

        $params = [$depositXml];
        $this->dispatcher->addDatasetRelationToCrossrefExport('articlecrossrefxmlfilter::execute', $params);

        return $depositXml;
    }

    private function countDatasetRelations(DOMDocument $depositXml): int
    {
        return $depositXml->getElementsByTagName('inter_work_relation')->count();
    }

    public function testAddsDatasetRelationWithoutRequestContext(): void
    {
        $this->enablePluginForContext();
        $this->configurePluginForContext();

        $expectedXml = file_get_contents(__DIR__ . '/../fixtures/crossref/expected/article_deposit.xml');

        $this->assertXmlStringEqualsXmlString($expectedXml, $this->dispatchDepositXml()->saveXML());
    }

    public function testSkipsDepositOfContextWithPluginDisabled(): void
    {
        $this->configurePluginForContext();

        $this->assertEquals(0, $this->countDatasetRelations($this->dispatchDepositXml()));
    }

    public function testSkipsDepositOfContextWithoutConfiguration(): void
    {
        $this->enablePluginForContext();

        $this->assertEquals(0, $this->countDatasetRelations($this->dispatchDepositXml()));
    }
}
