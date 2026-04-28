<?php

use PKP\tests\PKPTestCase;
use APP\plugins\generic\dataverse\classes\CrossrefXmlEditor;

class CrossrefXmlEditorTest extends PKPTestCase
{
    private CrossrefXmlEditor $xmlEditor;
    private DOMDocument $doc;
    private string $persistentId;

    public function setUp(): void
    {
        parent::setUp();

        $this->xmlEditor = new CrossrefXmlEditor();
        $this->doc = $this->createTestXml();
        $this->persistentId = 'doi:10.5072/FK2/ABCDEF';
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

        $expectedXml = file_get_contents(__DIR__ . '/fixtures/expected_dataset_relation.xml');

        $this->assertXmlStringEqualsXmlString($expectedXml, $resultXml);
    }
}
