<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.DraftDatasetFilesValidator');

class DraftDatasetFilesValidatorTest extends PKPTestCase
{
    public function testRequiresFileOtherThanReadmeEvenWithMultipleFiles(): void
    {
        $validator = new DraftDatasetFilesValidator();

        $this->assertFalse($validator->datasetHasNonReadmeFile([]));
        $this->assertFalse($validator->datasetHasNonReadmeFile([
            $this->fileNamed('README.txt'),
            $this->fileNamed('README.zip'),
        ]));
        $this->assertFalse($validator->datasetHasNonReadmeFile([
            $this->fileNamed('LEIA-ME.pdf'),
            $this->fileNamed('readme.txt'),
        ]));
        $this->assertTrue($validator->datasetHasNonReadmeFile([
            $this->fileNamed('README.txt'),
            $this->fileNamed('measurements.csv'),
        ]));
    }

    private function fileNamed(string $name): DraftDatasetFile
    {
        $file = new DraftDatasetFile();
        $file->setFileName($name);
        return $file;
    }
}
