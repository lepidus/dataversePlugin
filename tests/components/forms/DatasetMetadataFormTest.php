<?php

use APP\plugins\generic\dataverse\classes\components\forms\DatasetMetadataForm;
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
}
