<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.creators.DataverseDatasetDataCreator');

class DataverseDatasetDataCreatorTest extends PKPTestCase
{
    private $jsonPath = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'datasetData.json';
    private $creator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->creator = new DataverseDatasetDataCreator();
    }

    private function getTestDatasetData(): array
    {
        return [
            'title' => 'The Rise of the Empire Machine',
            'dsDescription' => ['<p>An example abstract</p>'],
            'keyword' => ['Modern History']
        ];
    }

    public function testIfCreatorReturnsObjectWithDatasetData(): void
    {
        $json = file_get_contents($this->jsonPath);
        $data = json_decode($json);

        $datasetData = $this->creator->createDatasetData($data->metadataBlocks->citation->fields);

        $datasetMetadata = [
            'title' => $datasetData->getData('title'),
            'dsDescription' => $datasetData->getData('dsDescription'),
            'keyword' => $datasetData->getData('keyword'),
        ];

        $this-> assertEquals($datasetMetadata, $this->getTestDatasetData());
    }

    public function testCreatorReturnsClassOfMetadata(): void
    {
        $metadata = $this->getTestDatasetData();

        $expectedObj = new stdClass();
        $expectedObj->dsDescriptionValue = new stdClass();
        $expectedObj->dsDescriptionValue->typeName = 'dsDescriptionValue';
        $expectedObj->dsDescriptionValue->multiple = false;
        $expectedObj->dsDescriptionValue->typeClass = 'primitive';
        $expectedObj->dsDescriptionValue->value = $metadata['dsDescription'];

        $obj = $this->creator->createCompoundObject('dsDescription', $metadata['dsDescription']);

        $this->assertEquals($expectedObj, $obj);
    }

    public function testCreatorReturnsMetatadaBlocks(): void
    {
        $json = file_get_contents($this->jsonPath);
        $data = json_decode($json);

        $metadata = [
            'datasetTitle' => 'Test Title',
            'datasetDescription' => ['Test description'],
            'datasetKeywords' => ['Test keyword'],
        ];

        $datasetMetadata = $data->metadataBlocks;

        $metadataBlocks = $this->creator->createMetadataFields($metadata);

        $expectedMetadataBlocks =& $data->metadataBlocks;
        $expectedMetadataBlocks->citation->fields[0]->value = $metadata['datasetTitle'];
        $expectedMetadataBlocks->citation->fields[1]->value[0]->dsDescriptionValue->value = $metadata['datasetDescription'][0];
        $expectedMetadataBlocks->citation->fields[2]->value[0]->keywordValue->value = $metadata['datasetKeywords'][0];

        $this->assertEquals($expectedMetadataBlocks->citation->fields, $metadataBlocks->fields);
    }
}