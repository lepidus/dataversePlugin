<?php

use APP\plugins\generic\dataverse\classes\DraftDatasetFilesValidator;
use PKP\tests\PKPTestCase;

class DraftDatasetFilesValidatorTest extends PKPTestCase
{
    public function testRecognizesSupportedReadmeNamesAndTypes(): void
    {
        $validator = new DraftDatasetFilesValidator();
        $method = new ReflectionMethod($validator, 'isReadmeFile');
        $method->setAccessible(true);

        $supportedFiles = [
            ['README.pdf', 'application/pdf'],
            ['project-readme.txt', 'text/plain'],
            ['LEIAME.PDF', 'application/pdf'],
            ['leia-me.txt', 'text/plain'],
            ['dados-leame.pdf', 'application/pdf'],
        ];

        foreach ($supportedFiles as [$fileName, $fileType]) {
            $this->assertTrue($method->invoke($validator, $fileName, $fileType), $fileName);
        }
    }

    public function testRejectsUnsupportedReadmeNamesAndTypes(): void
    {
        $validator = new DraftDatasetFilesValidator();
        $method = new ReflectionMethod($validator, 'isReadmeFile');
        $method->setAccessible(true);

        $unsupportedFiles = [
            ['data.pdf', 'application/pdf'],
            ['README.json', 'application/json'],
            ['leiame.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            ['read-me.txt', 'text/plain'],
        ];

        foreach ($unsupportedFiles as [$fileName, $fileType]) {
            $this->assertFalse($method->invoke($validator, $fileName, $fileType), $fileName);
        }
    }
}
