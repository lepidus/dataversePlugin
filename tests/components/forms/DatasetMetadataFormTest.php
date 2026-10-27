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
        $this->assertSame('date', $form->fields[0]->inputType);
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
