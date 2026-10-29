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

    public static function metadataFieldInputTypes(): array
    {
        return [
            'empty date' => ['DATE', null, 'date'],
            'full date' => ['DATE', '2023-06-01', 'date'],
            'year only' => ['DATE', '2023', 'text'],
            'year and month' => ['DATE', '2023-06', 'text'],
            'URL' => ['URL', 'https://example.com', 'text'],
            'text' => ['TEXT', 'value', 'text'],
        ];
    }

    #[PHPUnit\Framework\Attributes\DataProvider('metadataFieldInputTypes')]
    public function testOnlyEmptyOrFullDatesUseTheDateInput(string $type, ?string $value, string $inputType): void
    {
        $formReflection = new ReflectionClass(DatasetMetadataForm::class);
        $form = $formReflection->newInstanceWithoutConstructor();
        $addMetadataField = $formReflection->getMethod('addMetadataField');
        $dataset = new Dataset();
        $dataset->setData('collectionField', $value);

        $addMetadataField->invoke($form, [
            'name' => 'collectionField',
            'type' => $type,
            'displayName' => 'Collection Field',
            'description' => '',
            'isRequired' => true,
        ], $dataset);

        $this->assertSame($inputType, $form->fields[0]->inputType);
        $this->assertSame($value, $form->fields[0]->value);
    }

    public static function datasetsWithoutRelationType(): array
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

    #[PHPUnit\Framework\Attributes\DataProvider('datasetsWithoutRelationType')]
    public function testDatasetWithoutRelationTypeHasEmptyRelationType(Dataset $dataset): void
    {
        $formReflection = new ReflectionClass(DatasetMetadataForm::class);
        $form = $formReflection->newInstanceWithoutConstructor();
        $getDatasetMetadata = $formReflection->getMethod('getDatasetMetadata');

        $metadata = $getDatasetMetadata->invoke($form, $dataset);

        $this->assertNull($metadata['relationType']);
    }
}
