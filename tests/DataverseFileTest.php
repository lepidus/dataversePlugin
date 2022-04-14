<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.file.DataverseFile');

class DataverseFileTest extends PKPTestCase
{

    public function testHasStudyId(): void
    {
        $studyId = 1;

        $dataverseFile = new DataverseFile();
        $dataverseFile->setStudyId($studyId);

        $this->assertEquals($studyId, $dataverseFile->getStudyId());
    }

    public function testHasSubmissionId(): void
    {
        $submissionId = 1;

        $dataverseFile = new DataverseFile();
        $dataverseFile->setSubmissionId($submissionId);

        $this->assertEquals($submissionId, $dataverseFile->getSubmissionId());
    }

    public function testHasContentUri(): void
    {
        $contentUri = 'https://testuri/doi:ABDCFG/HIJL';

        $dataverseFile = new DataverseFile();
        $dataverseFile->setContentUri($contentUri);

        $this->assertEquals($contentUri, $dataverseFile->getContentUri());
    }

}

?>