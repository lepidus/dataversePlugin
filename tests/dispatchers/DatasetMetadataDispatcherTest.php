<?php

use APP\plugins\generic\dataverse\classes\dispatchers\DatasetMetadataDispatcher;
use PKP\tests\PKPTestCase;

class DatasetMetadataDispatcherTest extends PKPTestCase
{
    public function testAddsValidationErrorReturnedByMetadataFieldValidator(): void
    {
        $errors = [];

        $this->validateFieldByType(
            ['type' => 'URL'],
            'datasetAlternativeURL',
            'invalid-url',
            $errors
        );

        $this->assertArrayHasKey('datasetAlternativeURL', $errors);
        $this->assertNotEmpty($errors['datasetAlternativeURL']);
    }

    public function testDoesNotAddValidationErrorForValidValue(): void
    {
        $errors = [];

        $this->validateFieldByType(
            ['type' => 'URL'],
            'datasetAlternativeURL',
            'https://example.com/dataset',
            $errors
        );

        $this->assertSame([], $errors);
    }

    private function validateFieldByType(array $field, string $metadataName, string $value, array &$errors): void
    {
        $reflection = new ReflectionClass(DatasetMetadataDispatcher::class);
        $dispatcher = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('validateFieldByType');
        $method->setAccessible(true);
        $arguments = [$field, $metadataName, $value, &$errors];
        $method->invokeArgs($dispatcher, $arguments);
    }
}
