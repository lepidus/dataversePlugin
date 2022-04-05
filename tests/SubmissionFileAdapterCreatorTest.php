<?php

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.submission.SubmissionFile');
import('plugins.generic.dataverse.classes.creators.SubmissionFileAdapterCreator');

class SubmissionFileAdapterCreatorTest extends PKPTestCase
{

    private function createSubmissionFile(): SubmissionFile
    {
        $submissionFile = new SubmissionFile();
        $submissionFile->setData('genreId', 7);
        $submissionFile->setData('name', 'testSample.csv');
        $submissionFile->setData('path', 'path/to/file');
        $submissionFile->setData('publishData', true);
        $submissionFile->setData('sponsor', 'CAPES');

        return $submissionFile;
    }

    public function testFileAdapterRetrieveSubmissionFileData(): void
    {
        $creator = new SubmissionFileAdapterCreator();
        $submissionFile = $this->createSubmissionFile();
        $submissionFileAdapter = $creator->createSubmissionFileAdapter($submissionFile);

        $expectedFileData = [
            'genreId' => 7,
            'name' => 'testSample.csv',
            'path' => 'path/to/file',
            'publishData' => true,
            'sponsor' => 'CAPES'
        ];

        $submissionFileAdapterData = [
            'genreId' => $submissionFileAdapter->getGenreId(),
            'name' => $submissionFileAdapter->getName(),
            'path' => $submissionFileAdapter->getPath(),
            'publishData' => $submissionFileAdapter->getPublishData(),
            'sponsor' => $submissionFileAdapter->getSponsor()
        ];

        $this->assertEquals($expectedFileData, $submissionFileAdapterData);
    }

}

?>