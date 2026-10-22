<?php

use APP\plugins\generic\dataverse\classes\components\forms\DatasetMetadataForm;
use APP\plugins\generic\dataverse\classes\entities\Dataset;
use APP\plugins\generic\dataverse\classes\entities\DatasetRelatedPublication;
use PKP\tests\PKPTestCase;

class DatasetMetadataFormTest extends PKPTestCase
{
    public function testDuplicateMetadataFieldIsAddedOnlyOnce(): void
    {
        $formReflection = new ReflectionClass(DatasetMetadataForm::class);
        $form = $formReflection->newInstanceWithoutConstructor();
        $addMetadataField = $formReflection->getMethod('addMetadataField');
        $addMetadataField->setAccessible(true);
        $field = [
            'name' => 'distributionDate',
            'type' => 'DATE',
            'displayName' => 'Distribution Date',
            'description' => '',
            'isRequired' => true,
        ];

        $addMetadataField->invoke($form, $field, null);
        $addMetadataField->invoke($form, $field, null);

        $this->assertCount(1, $form->fields);
        $this->assertSame('datasetDistributionDate', $form->fields[0]->name);
    }

    public function datasetsWithoutRelationType(): array
    {
        $datasetWithoutRelatedPublication = new Dataset();
        $datasetWithUntypedRelatedPublication = new Dataset();
        $datasetWithUntypedRelatedPublication->setRelatedPublication(
            new DatasetRelatedPublication(null, 'Related article citation', null, null, null)
        );

        return [
            'without related publication' => [$datasetWithoutRelatedPublication],
            'with untyped related publication' => [$datasetWithUntypedRelatedPublication],
        ];
    }

    /**
     * @dataProvider datasetsWithoutRelationType
     */
    public function testDatasetWithoutRelationTypeHasEmptyRelationType(Dataset $dataset): void
    {
        $formReflection = new ReflectionClass(DatasetMetadataForm::class);
        $form = $formReflection->newInstanceWithoutConstructor();
        $getDatasetMetadata = $formReflection->getMethod('getDatasetMetadata');
        $getDatasetMetadata->setAccessible(true);

        $metadata = $getDatasetMetadata->invoke($form, $dataset);

        $this->assertNull($metadata['relationType']);
    }
    /** @dataProvider metadataFieldTypes */
    public function testMetadataFieldUsesExpectedControl(
        string $type,
        bool $controlledVocabulary,
        string $expectedComponent
    ): void {
        $reflection = new ReflectionClass(DatasetMetadataForm::class);
        $form = $reflection->newInstanceWithoutConstructor();
        $addMetadataField = $reflection->getMethod('addMetadataField');
        $addMetadataField->setAccessible(true);
        $dataset = new \APP\plugins\generic\dataverse\classes\entities\Dataset();
        $dataset->setData('dsDescriptionDate', '2026-10-19');

        $addMetadataField->invoke($form, [
            'name' => 'dsDescriptionDate',
            'type' => $type,
            'displayName' => 'Description Date',
            'description' => 'Date of the description',
            'isRequired' => true,
            'isControlledVocabulary' => $controlledVocabulary,
            'controlledVocabularyValues' => ['2026-10-19'],
        ], $dataset);

        $config = $form->fields[0]->getConfig();
        $this->assertSame($expectedComponent, $config['component']);
        $this->assertSame('2026-10-19', $config['value']);
        $this->assertSame('datasetDsDescriptionDate', $config['name']);
        $this->assertTrue($config['isRequired']);
    }

    public function metadataFieldTypes(): array
    {
        return [
            'date uses the core calendar' => ['DATE', false, 'field-datepicker'],
            'text keeps its text control' => ['TEXT', false, 'field-text'],
            'controlled dates keep their options' => ['DATE', true, 'field-select'],
        ];
    }

}
