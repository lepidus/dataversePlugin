<?php

use APP\plugins\generic\dataverse\classes\validation\RequiredMetadataFieldValidator;
use PKP\tests\PKPTestCase;

class RequiredMetadataFieldValidatorTest extends PKPTestCase
{
    /**
     * @dataProvider validValuesProvider
     */
    public function testAcceptsValidValues(string $type, string $value): void
    {
        $this->assertSame([], (new RequiredMetadataFieldValidator())->validate($type, $value));
    }

    public function validValuesProvider(): array
    {
        return [
            'date' => ['DATE', '2023-06-01'],
            'url' => ['URL', 'https://example.com/dataset?id=1'],
            'email' => ['EMAIL', 'researcher@example.com'],
            'unknown Dataverse type' => ['TEXTBOX', 'any value'],
        ];
    }

    /**
     * @dataProvider invalidValuesProvider
     */
    public function testRejectsInvalidValues(string $type, string $value): void
    {
        $this->assertNotEmpty((new RequiredMetadataFieldValidator())->validate($type, $value));
    }

    public function invalidValuesProvider(): array
    {
        return [
            'date with impossible day' => ['DATE', '2023-06-32'],
            'date in non-ISO format' => ['DATE', 'June 1, 2023'],
            'url' => ['URL', 'invalid-url'],
            'email' => ['EMAIL', 'invalid-email'],
        ];
    }
}
