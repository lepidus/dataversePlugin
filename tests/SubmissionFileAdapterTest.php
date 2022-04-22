<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.adapters.SubmissionFileAdapter');

class SubmissionFileAdapterTest extends PKPTestCase
{

    // public function testHasSubmissionFileId(): void
    // {
    //     $genreId = DATASET_GENRE_ID;
    //     $submissionFileAdapter = new SubmissionFileAdapter($genreId, '', '', false, 'N/A');

    //     $this->assertEquals($genreId, $submissionFileAdapter->getGenreId());
    // }

    public function testHasDatasetGenreId(): void
    {
        $genreId = DATASET_GENRE_ID;
        $submissionFileAdapter = new SubmissionFileAdapter(1, $genreId, '', '', false, 'N/A');

        $this->assertEquals($genreId, $submissionFileAdapter->getGenreId());
    }

    public function testFileCanBePublished(): void
    {
        $publishData = true;
        $submissionFileAdapter = new SubmissionFileAdapter(1, DATASET_GENRE_ID, '', '', $publishData, 'N/A');

        $this->assertTrue($submissionFileAdapter->getPublishData());
    }

    public function testHasDatasetSponsor(): void
    {
        $sponsor = 'CAPES';
        $submissionFileAdapter = new SubmissionFileAdapter(1, DATASET_GENRE_ID, '', '', false, $sponsor);

        $this->assertEquals($sponsor, $submissionFileAdapter->getSponsor());
    }

    public function testHasSubmissionFileName(): void
    {
        $name = 'testSample.csv';
        $submissionFileAdapter = new SubmissionFileAdapter(1, DATASET_GENRE_ID, $name, '', false, 'N/A');

        $this->assertEquals($name, $submissionFileAdapter->getName());
    }

    public function testHasSubmissionFilePath(): void
    {
        $path = 'path/to/file';
        $submissionFileAdapter = new SubmissionFileAdapter(1, DATASET_GENRE_ID, '', $path, false, 'N/A');

        $this->assertEquals($path, $submissionFileAdapter->getPath());
    }
}

?>