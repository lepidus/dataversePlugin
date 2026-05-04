<?php

import('lib.pkp.tests.DatabaseTestCase');
import('plugins.generic.dataverse.classes.CrossrefXmlEditor');
import('plugins.generic.dataverse.classes.dataverseStudy.DataverseStudy');
import('plugins.generic.dataverse.classes.dataverseStudy.DataverseStudyDAO');
import('plugins.generic.dataverse.classes.entities.Dataset');
import('plugins.generic.dataverse.dataverseAPI.actions.DatasetActions');
import('plugins.generic.dataverse.classes.dispatchers.DataStatementDispatcher');
import('plugins.generic.dataverse.DataversePlugin');

use Illuminate\Database\Capsule\Manager as Capsule;

class CrossrefXmlEditorTest extends DatabaseTestCase
{
    private $xmlEditor;
    private $doc;
    private $contextId = 1;
    private $submissionId = null;
    private $doiId = null;
    private $doi = '10.1234/PublicKnowledge.17';
    private $study = null;
    private $dataset = null;
    private $persistentId = 'doi:10.5072/FK2/ABCDEF';

    public function setUp(): void
    {
        parent::setUp();
        $plugin = new DataversePlugin();
        $dispatcher = new DataStatementDispatcher($plugin);

        $this->doc = $this->createTestXml();
        $this->submissionId = $this->createTestSubmission();
        $this->study = $this->createDataverseStudy();
        $this->dataset = $this->createTestDataset();
        $this->xmlEditor = $this->createXmlEditor();
    }

    protected function getAffectedTables(): array
    {
        return ['submissions', 'submission_settings', 'publications', 'publication_settings', 'dataverse_studies'];
    }

    private function createTestSubmission(): int
    {
        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        $submission = $submissionDao->newDataObject();
        $submission->setData('contextId', $this->contextId);
        $submissionId = $submissionDao->insertObject($submission);

        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $publication = $publicationDao->newDataObject();
        $publication->setData('pub-id::doi', $this->doi);
        $publication->setData('submissionId', $submissionId);
        $pubId = $publicationDao->insertObject($publication);

        Capsule::table('publication_settings')->insert([
            'publication_id' => $pubId,
            'locale' => '',
            'setting_name' => 'pub-id::doi',
            'setting_value' => $this->doi,
        ]);

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

        $dataverseStudyDao = new DataverseStudyDAO();
        $studyId = $dataverseStudyDao->insertStudy($study);
        $study->setId($studyId);

        return $study;
    }

    private function createTestXml()
    {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->appendChild($xml->createElement('work'));

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
        $workNode = $this->doc->documentElement;

        $result = $this->xmlEditor->addDatasetRelationToWorkNode($workNode, $this->persistentId);

        $programNode = $result->getElementsByTagNameNS('http://www.crossref.org/relations.xsd', 'program')->item(0);
        $resultXml = $result->ownerDocument->saveXML($programNode);

        $expectedXml = file_get_contents(__DIR__ . '/fixtures/crossref/expected/dataset_relation.xml');

        $this->assertXmlStringEqualsXmlString($expectedXml, $resultXml);
    }

    public function testAddsDatasetRelationToDepositXml(): void
    {
        $this->assertAddingOfRelationToXmlMatchesExpected('preprint_deposit.xml');
        $this->assertAddingOfRelationToXmlMatchesExpected('article_deposit.xml');
    }

    public function testAddsRelationToXmlAlreadyWithRelation(): void
    {
        $this->assertAddingOfRelationToXmlMatchesExpected('preprint_deposit_with_relation.xml');
    }

    private function assertAddingOfRelationToXmlMatchesExpected(string $fixture): void
    {
        $depositXml = new DOMDocument();
        $depositXml->load(__DIR__ . '/fixtures/crossref/' . $fixture);

        $result = $this->xmlEditor->addDatasetRelationToDepositXml($depositXml);

        $expectedXml = file_get_contents(__DIR__ . '/fixtures/crossref/expected/' . $fixture);

        $this->assertXmlStringEqualsXmlString($expectedXml, $result->saveXML());
    }
}
