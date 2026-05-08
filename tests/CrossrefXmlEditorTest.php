<?php

import('lib.pkp.tests.DatabaseTestCase');
import('plugins.generic.dataverse.classes.CrossrefXmlEditor');
import('plugins.generic.dataverse.classes.dataverseStudy.DataverseStudy');
import('plugins.generic.dataverse.classes.dataverseStudy.DataverseStudyDAO');
import('plugins.generic.dataverse.classes.entities.Dataset');
import('plugins.generic.dataverse.dataverseAPI.actions.DatasetActions');
import('plugins.generic.dataverse.classes.services.DataStatementService');
import('plugins.generic.dataverse.classes.dispatchers.DataStatementDispatcher');
import('plugins.generic.dataverse.DataversePlugin');

use Illuminate\Database\Capsule\Manager as Capsule;

class CrossrefXmlEditorTest extends DatabaseTestCase
{
    private $xmlEditor;
    private $contextId = 1;
    private $submission;
    private $publication;
    private $doi = '10.1234/PublicKnowledge.17';
    private $study = null;
    private $dataset = null;
    private $persistentId = 'doi:10.5072/FK2/ABCDEF';
    private $externalDatasetUrl = 'https://doi.org/10.1234/zenodo.98765';

    public function setUp(): void
    {
        parent::setUp();
        $plugin = new DataversePlugin();
        $dispatcher = new DataStatementDispatcher($plugin);

        $this->createTestSubmission();
        $this->study = $this->createDataverseStudy();
        $this->dataset = $this->createTestDataset();
        $this->xmlEditor = $this->createXmlEditor();
    }

    protected function getAffectedTables(): array
    {
        return ['submissions', 'submission_settings', 'publications', 'publication_settings', 'dataverse_studies'];
    }

    private function createTestSubmission()
    {
        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        $submission = $submissionDao->newDataObject();
        $submission->setData('contextId', $this->contextId);
        $submissionId = $submissionDao->insertObject($submission);

        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $publication = $publicationDao->newDataObject();
        $publication->setData('submissionId', $submissionId);
        $pubId = $publicationDao->insertObject($publication);

        Capsule::table('publication_settings')->insert([
            'publication_id' => $pubId,
            'locale' => '',
            'setting_name' => 'pub-id::doi',
            'setting_value' => $this->doi,
        ]);

        $this->submission = $submissionDao->getById($submissionId);
        $this->publication = $this->submission->getCurrentPublication();
    }

    private function addExternalDatasetsToPublication()
    {
        $this->publication->setData('dataStatementTypes', [DATA_STATEMENT_TYPE_REPO_AVAILABLE]);
        $this->publication->setData('dataStatementUrls', [$this->externalDatasetUrl]);

        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $publicationDao->updateObject($this->publication);
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

        $dataverseStudyDao = new DataverseStudyDAO();
        $studyId = $dataverseStudyDao->insertStudy($study);
        $study->setId($studyId);

        return $study;
    }

    private function openTestXml(string $fixture): DomDocument
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
        $worksXml = $this->openTestXml('work_node.xml');
        $doi = preg_replace('/^doi:/i', '', $this->persistentId);

        $noPreviousRelWorkNode = $worksXml->getElementsByTagName('work')->item(0);
        $this->xmlEditor->addDatasetRelationToWorkNode($noPreviousRelWorkNode, $doi);

        $withPreviousRelWorkNode = $worksXml->getElementsByTagName('work')->item(1);
        $this->xmlEditor->addDatasetRelationToWorkNode($withPreviousRelWorkNode, $doi);

        $resultXml = $worksXml->saveXML();
        $expectedXml = file_get_contents(__DIR__ . '/fixtures/crossref/expected/work_node.xml');

        $this->assertXmlStringEqualsXmlString($expectedXml, $resultXml);
    }

    public function testAddsExternalDatasetRelationToWorkNode(): void
    {
        $worksXml = $this->openTestXml('work_node.xml');

        $noPreviousRelWorkNode = $worksXml->getElementsByTagName('work')->item(0);
        $this->xmlEditor->addDatasetRelationToWorkNode($noPreviousRelWorkNode, $this->externalDatasetUrl, true);

        $withPreviousRelWorkNode = $worksXml->getElementsByTagName('work')->item(1);
        $this->xmlEditor->addDatasetRelationToWorkNode($withPreviousRelWorkNode, $this->externalDatasetUrl, true);

        $resultXml = $worksXml->saveXML();
        $expectedXml = file_get_contents(__DIR__ . '/fixtures/crossref/expected/work_node_external.xml');

        $this->assertXmlStringEqualsXmlString($expectedXml, $resultXml);
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

    private function assertAddingOfRelationToXmlMatchesExpected(string $fixture, string $expectedFixture): void
    {
        $depositXml = new DOMDocument();
        $depositXml->load(__DIR__ . '/fixtures/crossref/' . $fixture);

        $result = $this->xmlEditor->addDatasetRelationToDepositXml($depositXml);

        $expectedXml = file_get_contents(__DIR__ . '/fixtures/crossref/expected/' . $expectedFixture);

        $this->assertXmlStringEqualsXmlString($expectedXml, $result->saveXML());
    }
}
