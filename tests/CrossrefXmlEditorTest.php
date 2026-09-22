<?php

use PKP\doi\Doi;
use PKP\tests\DatabaseTestCase;
use APP\publication\Publication;
use APP\submission\Submission;
use APP\plugins\generic\dataverse\classes\CrossrefXmlEditor;
use APP\plugins\generic\dataverse\classes\dataverseStudy\DataverseStudy;
use APP\plugins\generic\dataverse\classes\entities\Dataset;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DatasetActions;
use APP\plugins\generic\dataverse\classes\services\DataStatementService;
use APP\plugins\generic\dataverse\classes\dispatchers\DataStatementDispatcher;
use APP\plugins\generic\dataverse\tests\helpers\CreatesTestContext;
use APP\plugins\generic\dataverse\DataversePlugin;

class CrossrefXmlEditorTest extends DatabaseTestCase
{
    use CreatesTestContext;

    private CrossrefXmlEditor $xmlEditor;
    private $context;
    private ?Submission $submission = null;
    private ?Publication $publication = null;
    private ?int $doiId = null;
    private string $doi = '10.1234/PublicKnowledge.17';
    private ?DataverseStudy $study = null;
    private ?Dataset $dataset = null;
    private string $persistentId = 'doi:10.5072/FK2/ABCDEF';
    private string $externalDatasetUrl = 'https://doi.org/10.1234/zenodo.98765';

    public function setUp(): void
    {
        parent::setUp();
        $plugin = new DataversePlugin();
        $dispatcher = new DataStatementDispatcher($plugin);

        $this->context = $this->createTestContext();
        $this->createTestSubmission();
        $this->study = $this->createDataverseStudy();
        $this->dataset = $this->createTestDataset();
        $this->xmlEditor = $this->createXmlEditor();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        Repo::submission()->delete($this->submission);

        $doi = Repo::doi()->get($this->doiId);
        if ($doi) {
            Repo::doi()->delete($doi);
        }

        $this->deleteTestContext($this->context);
    }

    private function createTestSubmission()
    {
        $submission = new Submission();
        $submission->setData('contextId', $this->context->getId());
        $publication = new Publication();

        $submissionId = Repo::submission()->add($submission, $publication, $this->context);
        $this->submission = Repo::submission()->get($submissionId);

        $doi = Repo::doi()->newDataObject([
            'contextId' => $this->context->getId(),
            'doi' => $this->doi,
            'status' => Doi::STATUS_REGISTERED,
        ]);
        $this->doiId = Repo::doi()->add($doi);

        $this->publication = $this->submission->getCurrentPublication();
        $this->publication->setData('doiId', $this->doiId);
        Repo::publication()->dao->update($this->publication);
    }

    private function addExternalDatasetsToPublication()
    {
        $this->publication->setData('dataStatementTypes', [DataStatementService::DATA_STATEMENT_TYPE_REPO_AVAILABLE]);
        $this->publication->setData('dataStatementUrls', [$this->externalDatasetUrl]);

        Repo::publication()->dao->update($this->publication);
    }

    private function createDataverseStudy(): DataverseStudy
    {
        $study = new DataverseStudy();
        $study->setSubmissionId($this->submission->getId());
        $study->setEditUri('https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit/study/' . $this->persistentId);
        $study->setEditMediaUri('https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit-media/study/' . $this->persistentId);
        $study->setStatementUri('https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/statement/study/' . $this->persistentId);
        $study->setPersistentUri('https://doi.org/10.5072/FK2/ABCDEF');
        $study->setPersistentId($this->persistentId);

        $id = Repo::dataverseStudy()->add($study);
        $study->setId($id);

        return $study;
    }

    private function openTestXml(string $fixture): DOMDocument
    {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->load(__DIR__ . '/fixtures/crossref/' . $fixture);

        return $xml;
    }

    private function createTestDataset(): Dataset
    {
        $dataset = new Dataset();
        $dataset->setPersistentId($this->persistentId);
        $dataset->setVersionState(Dataset::VERSION_STATE_RELEASED);

        return $dataset;
    }

    private function createXmlEditor(): CrossrefXmlEditor
    {
        $mockDatasetActions = $this->createMock(DatasetActions::class);
        $mockDatasetActions->method('get')->willReturn($this->dataset);

        return new CrossrefXmlEditor($mockDatasetActions);
    }

    public function testAddsDatasetRelationToWorkNode(): void
    {
        $doi = preg_replace('/^doi:/i', '', $this->persistentId);

        $this->assertAddingOfRelationToWorkNodeMatchesExpected(
            'preprint_deposit_versioned.xml',
            'posted_content',
            $doi,
            false,
            'preprint_deposit_versioned.xml'
        );
    }

    public function testAddsExternalDatasetRelationToWorkNode(): void
    {
        $this->assertAddingOfRelationToWorkNodeMatchesExpected(
            'article_deposit.xml',
            'journal_article',
            $this->externalDatasetUrl,
            true,
            'article_deposit_external.xml'
        );
        $this->assertAddingOfRelationToWorkNodeMatchesExpected(
            'preprint_deposit_versioned.xml',
            'posted_content',
            $this->externalDatasetUrl,
            true,
            'preprint_deposit_versioned_external.xml'
        );
    }

    public function testAddsDatasetRelationToDepositXml(): void
    {
        $this->assertAddingOfRelationToXmlMatchesExpected('preprint_deposit.xml', 'preprint_deposit.xml');
        $this->assertAddingOfRelationToXmlMatchesExpected('article_deposit.xml', 'article_deposit.xml');
    }

    public function testAddsExternalDatasetRelationToDepositXml(): void
    {
        $this->addExternalDatasetsToPublication();

        $this->assertAddingOfRelationToXmlMatchesExpected('preprint_deposit.xml', 'preprint_deposit_external.xml');
    }

    private function assertAddingOfRelationToWorkNodeMatchesExpected(
        string $fixture,
        string $workNodeName,
        string $identifier,
        bool $isExternalDataset,
        string $expectedFixture
    ): void {
        $depositXml = $this->openTestXml($fixture);
        $workNode = $depositXml->getElementsByTagName($workNodeName)->item(0);

        $this->xmlEditor->addDatasetRelationToWorkNode($workNode, $identifier, $isExternalDataset);

        $expectedXml = file_get_contents(__DIR__ . '/fixtures/crossref/expected/' . $expectedFixture);

        $this->assertXmlStringEqualsXmlString($expectedXml, $depositXml->saveXML());
    }

    private function assertAddingOfRelationToXmlMatchesExpected(string $fixture, string $expectedFixture): void
    {
        $depositXml = $this->openTestXml($fixture);

        $result = $this->xmlEditor->addDatasetRelationToDepositXml($depositXml, $this->context->getId());

        $expectedXml = file_get_contents(__DIR__ . '/fixtures/crossref/expected/' . $expectedFixture);

        $this->assertXmlStringEqualsXmlString($expectedXml, $result->saveXML());
    }
}
