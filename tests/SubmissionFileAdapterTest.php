<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.adapters.SubmissionFileAdapter');

class SubmissionFileAdapterTest extends PKPTestCase
{
    public function testIfAdapterHasFilePath(): void
    {
        $path = './assets/testSample.csv';
        $name = 'TestSample';

        $submissionFileAdapter = new SubmissionFileAdapter($path, $name, true);
        $this->assertEquals($path, $submissionFileAdapter->getPath());
    }

    public function testIfAdapterHasFileName(): void
    {
        $path = './assets/testSample.csv';
        $name = 'TestSample';

        $submissionFileAdapter = new SubmissionFileAdapter($path, $name, true);
        $this->assertEquals($name, $submissionFileAdapter->getName());
    }
}
