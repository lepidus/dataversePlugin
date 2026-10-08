<?php

use APP\facades\Repo;
use APP\plugins\generic\dataverse\classes\components\forms\DatasetMetadataForm;
use APP\plugins\generic\dataverse\classes\facades\Repo as DataverseRepo;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use APP\plugins\generic\dataverse\tests\helpers\DataverseIntegrationFixture;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FieldText;
use PKP\tests\DatabaseTestCase;

class CollectionRequiredMetadataSubmissionTest extends DatabaseTestCase
{
    use DataverseIntegrationFixture;

    private const REQUIRED_METADATA = [
        'datasetAlternativeURL' => 'https://example.com',
        'datasetDsDescriptionDate' => '2023-06-01',
        'datasetPSRI1' => 'Yes',
        'datasetPSRI2' => 'No',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFixture();
        $this->configureWithRealDataverse($this->collectionWithRequiredMetadataUrl());
        $this->registerPlugin();
    }

    protected function tearDown(): void
    {
        try {
            $this->deleteDatasetsFromDataverse();
        } finally {
            $this->tearDownFixture();
            parent::tearDown();
            $this->restoreCoreSchemas();
        }
    }

    private function validateSubmission(array $requiredMetadata): array
    {
        $submission = $this->createSubmissionWithResearchData(array_merge(
            ['datasetSubject' => 'Earth and Environmental Sciences'],
            $requiredMetadata
        ));

        return Repo::submission()->validateSubmit($submission, $this->context);
    }

    public function testCollectionRequiredMetadataIsAddedToTheDatasetMetadataForm(): void
    {
        $form = new DatasetMetadataForm('', 'POST', null, 'submission');

        $expectedFields = [
            'datasetAlternativeURL' => [FieldText::class, 'Alternative URL'],
            'datasetDsDescriptionDate' => [FieldText::class, 'Description Date'],
            'datasetPSRI1' => [FieldSelect::class, 'Are the original data publicly available?'],
            'datasetPSRI2' => [FieldSelect::class, 'Is the original code available?'],
        ];
        foreach ($expectedFields as $name => [$fieldClass, $label]) {
            $field = $form->getField($name);
            $this->assertInstanceOf($fieldClass, $field, $name);
            $this->assertEquals($label, $field->label);
            $this->assertTrue($field->isRequired);
        }
        $this->assertContains('Yes', array_column($form->getField('datasetPSRI1')->options, 'value'));
    }

    public function testCollectionRequiredMetadataIsRequired(): void
    {
        $errors = $this->validateSubmission([]);

        foreach (array_keys(self::REQUIRED_METADATA) as $name) {
            $this->assertEquals([__('validator.required')], $errors[$name] ?? null, $name);
        }
    }

    public function testCollectionRequiredMetadataMustHaveValidValues(): void
    {
        $errors = $this->validateSubmission(array_merge(self::REQUIRED_METADATA, [
            'datasetAlternativeURL' => 'invalid-url',
            'datasetDsDescriptionDate' => 'june 32, 2023',
        ]));

        $this->assertEquals([__('validator.url')], $errors['datasetAlternativeURL']);
        $this->assertContains(__('validator.date'), $errors['datasetDsDescriptionDate']);
        $this->assertArrayNotHasKey('datasetPSRI1', $errors);
        $this->assertArrayNotHasKey('datasetPSRI2', $errors);
    }

    public function testSubmissionWithCollectionRequiredMetadataIsDeposited(): void
    {
        $submission = $this->createSubmissionWithResearchData(array_merge(
            ['datasetSubject' => 'Earth and Environmental Sciences'],
            self::REQUIRED_METADATA
        ));

        $errors = Repo::submission()->validateSubmit($submission, $this->context);
        $this->assertEmpty(array_intersect_key($errors, self::REQUIRED_METADATA));
        Repo::submission()->submit($submission, $this->context);

        $study = DataverseRepo::dataverseStudy()->getBySubmissionId($submission->getId());
        $this->assertNotNull($study);
        $dataset = (new DataverseClient())->getDatasetActions()->get($study->getPersistentId());
        $this->assertEquals('https://example.com', $dataset->getData('alternativeURL'));
        $this->assertEquals('2023-06-01', $dataset->getData('dsDescriptionDate'));
        $this->assertEquals('Yes', $dataset->getData('PSRI1'));
        $this->assertEquals('No', $dataset->getData('PSRI2'));
    }
}
