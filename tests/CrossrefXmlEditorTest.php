<?php

use PKP\tests\PKPTestCase;
use APP\plugins\generic\dataverse\classes\entities\Dataset;
use APP\plugins\generic\dataverse\classes\CrossrefXmlEditor;

class CrossrefXmlEditorTest extends PKPTestCase
{
    private CrossrefXmlEditor $xmlEditor;
    private DOMDocument $doc;
    private Dataset $dataset;

    public function setUp(): void
    {
        parent::setUp();

        $this->xmlEditor = new CrossrefXmlEditor();
        $this->doc = $this->createTestXml();
        $this->dataset = $this->createTestDataset();
    }

    private function createTestXml()
    {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->appendChild($xml->createElement('work'));

        return $xml;
    }

    private function createTestDataset()
    {
        $dataset = new Dataset();
        $dataset->setPersistentId('doi:10.5072/FK2/ABCDEF');

        return $dataset;
    }

    public function testAddsDatasetRelationToWorkNode(): void
    {
        $workNode = $this->doc->documentElement;

        $result = $this->xmlEditor->addDatasetRelationToWorkNode($workNode, $this->dataset);

        $programNode = $result->getElementsByTagNameNS('http://www.crossref.org/relations.xsd', 'program')->item(0);
        $resultXml = $result->ownerDocument->saveXML($programNode);

        $expectedXml = file_get_contents(__DIR__ . '/fixtures/expected_dataset_relation.xml');

        $this->assertXmlStringEqualsXmlString($expectedXml, $resultXml);
    }
}
