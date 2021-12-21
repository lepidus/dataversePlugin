<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.creators.SubmissionFileFactory');

class SubmissionFileFactoryTest extends PKPTestCase
{
    public function testSubmissionFileAdapterHasPublicFilesDirectoryInFilePath(): void
    {
        $submissionFile = new SubmissionFile();
        $submissionFile->setData('locale', 'en_US');
		$submissionFile->setData('path', '/assets/testSample.csv');
		$submissionFile->setData('name', 'sampleFileForTests.csv');
		$submissionFile->setData('publishData', true);

        $factory = new SubmissionFileFactory();
        $submissionFileAdapter = $factory->build($submissionFile);
        $publicFilesDir = Config::getVar('files', 'files_dir');
        $expectedFilePath = $publicFilesDir. '/assets/testSample.csv';

        $this->assertEquals($expectedFilePath, $submissionFileAdapter->getPath());
    }
}
