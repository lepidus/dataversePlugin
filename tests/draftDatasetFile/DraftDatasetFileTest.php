<?php

import('lib.pkp.tests.PKPTestCase');

class DraftDatasetFileTest extends PKPTestCase
{
    public function testGettersAndSetters(): void
    {
        $draftDatasetFile = new DraftDatasetFile();
        $draftDatasetFile->setId(100);
        $draftDatasetFile->setSubmissionId(200);
        $draftDatasetFile->setUserId(300);
        $draftDatasetFile->setFileId(400);
        $draftDatasetFile->setFileName('example.pdf');

        $this->assertEquals(100, $draftDatasetFile->getId());
        $this->assertEquals(200, $draftDatasetFile->getSubmissionId());
        $this->assertEquals(300, $draftDatasetFile->getUserId());
        $this->assertEquals(400, $draftDatasetFile->getFileId());
        $this->assertEquals('example.pdf', $draftDatasetFile->getFileName());
    }
}
