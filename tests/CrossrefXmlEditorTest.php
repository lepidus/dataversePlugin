<?php

use PKP\doi\Doi;
use PKP\tests\DatabaseTestCase;
use APP\core\Application;
use APP\publication\Publication;
use APP\submission\Submission;
use APP\plugins\generic\dataverse\classes\CrossrefXmlEditor;
use APP\plugins\generic\dataverse\classes\dataverseStudy\DataverseStudy;
use APP\plugins\generic\dataverse\classes\facades\Repo;

class CrossrefXmlEditorTest extends DatabaseTestCase
{
    private CrossrefXmlEditor $xmlEditor;
    private DOMDocument $doc;
    private $contextId = 1;
    private ?int $submissionId = null;
    private ?int $doiId = null;
    private string $doi = '10.1234/PublicKnowledge.17';
    private ?DataverseStudy $study = null;
    private string $persistentId = 'doi:10.5072/FK2/ABCDEF';

    public function setUp(): void
    {
        parent::setUp();

        $this->xmlEditor = new CrossrefXmlEditor();
        $this->doc = $this->createTestXml();
        $this->submissionId = $this->createTestSubmission();
        $this->study = $this->createDataverseStudy();
    }

    public function tearDown(): void
    {
        $submission = Repo::submission()->get($this->submissionId);
        if ($submission) {
            Repo::submission()->delete($submission);
        }

        $doi = Repo::doi()->get($this->doiId);
        if ($doi) {
            Repo::doi()->delete($doi);
        }

        parent::tearDown();
    }

    protected function getAffectedTables(): array
    {
        return [...parent::getAffectedTables(), 'dataverse_studies'];
    }

    private function createTestSubmission(): int
    {
        $context = Application::getContextDAO()->getById($this->contextId);

        $submission = new Submission();
        $submission->setData('contextId', $this->contextId);
        $publication = new Publication();

        $submissionId = Repo::submission()->add($submission, $publication, $context);
        $submission = Repo::submission()->get($submissionId);

        $doi = Repo::doi()->newDataObject([
            'contextId' => $this->contextId,
            'doi' => $this->doi,
            'status' => Doi::STATUS_REGISTERED,
        ]);
        $this->doiId = Repo::doi()->add($doi);

        $publication = Repo::publication()->get($submission->getData('currentPublicationId'));
        $publication->setData('doiId', $this->doiId);
        Repo::publication()->dao->update($publication);

        return $submissionId;
    }

    private function createDataverseStudy(): DataverseStudy
    {
        $study = new DataverseStudy();
        $study->setSubmissionId($this->submissionId);
        $study->setEditUri('https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit/study/' . $this->persistentId);
        $study->setEditMediaUri('https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit-media/study/' . $this->persistentId);
        $study->setStatementUri('https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/statement/study/' . $this->persistentId);
        $study->setPersistentUri('https://doi.org/10.5072/FK2/ABCDEF');
        $study->setPersistentId($this->persistentId);

        $id = Repo::dataverseStudy()->add($study);
        $study->setId($id);

        return $study;
    }

    private function createTestXml()
    {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->appendChild($xml->createElement('work'));

        return $xml;
    }

    public function testAddsDatasetRelationToWorkNode(): void
    {
        $workNode = $this->doc->documentElement;

        $result = $this->xmlEditor->addDatasetRelationToWorkNode($workNode, $this->persistentId);

        $programNode = $result->getElementsByTagNameNS('http://www.crossref.org/relations.xsd', 'program')->item(0);
        $resultXml = $result->ownerDocument->saveXML($programNode);

        $expectedXml = file_get_contents(__DIR__ . '/fixtures/crossref/expected_dataset_relation.xml');

        $this->assertXmlStringEqualsXmlString($expectedXml, $resultXml);
    }

    public function testAddsDatasetRelationToDepositXml(): void
    {
        $this->assertAddingOfRelationToXmlMatchesExpected('preprint_deposit.xml', 'expected_preprint_deposit.xml');
        $this->assertAddingOfRelationToXmlMatchesExpected('article_deposit.xml', 'expected_article_deposit.xml');
    }

    private function assertAddingOfRelationToXmlMatchesExpected(string $fixture, string $expectedFixture): void
    {
        $depositXml = new DOMDocument();
        $depositXml->load(__DIR__ . '/fixtures/crossref/' . $fixture);

        $result = $this->xmlEditor->addDatasetRelationToDepositXml($depositXml);

        $expectedXml = file_get_contents(__DIR__ . '/fixtures/crossref/' . $expectedFixture);

        $this->assertXmlStringEqualsXmlString($expectedXml, $result->saveXML());
    }
}
