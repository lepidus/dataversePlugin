<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.adapters.SubmissionFileAdapter');

class SubmissionFileAdapterTest extends PKPTestCase
{

    function testHasDatasetGenreId(): void
    {
        $genreId = DATASET_GENRE_ID;
        $submissionFileAdapter = new SubmissionFileAdapter($genreId, false, 'N/A');

        $this->assertEquals($genreId, $submissionFileAdapter->getGenreId());
    }

    function testFileCanBePublished(): void
    {
        $publishData = true;
        $submissionFileAdapter = new SubmissionFileAdapter(DATASET_GENRE_ID, $publishData, 'N/A');

        $this->assertTrue($submissionFileAdapter->getPublishData());
    }

    function testHasDatasetSponsor(): void
    {
        $sponsor = 'CAPES';
        $submissionFileAdapter = new SubmissionFileAdapter(DATASET_GENRE_ID, false, $sponsor);

        $this->assertEquals($sponsor, $submissionFileAdapter->getSponsor());
    }
}

?>