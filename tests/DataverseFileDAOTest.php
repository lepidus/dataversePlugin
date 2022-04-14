<?php

import('lib.pkp.tests.DatabaseTestCase');
import('plugins.generic.dataverse.classes.file.DataverseFile');
import('plugins.generic.dataverse.classes.file.DataverseFileDAO');
import('lib.pkp.classes.db.DAO');

class DataverseFileDAOTest extends DatabaseTestCase
{
    private $dataverseFile;
    
    public function setUp(): void
    {
        parent::setUp();
        $this->dataverseFile = $this->createTestDataverseFile();
    }
    
    protected function getAffectedTables()
    {
		return array("dataverse_files");
	}

    private function createTestDataverseFile(): DataverseFile
    {
        $dataverseFile = new DataverseFile();
        $dataverseFile->setStudyId(1);
        $dataverseFile->setSubmissionId(1);
        $dataverseFile->setSubmissionFileId(1);
        $dataverseFile->setContentUri('https://testcontenturi/');

        return $dataverseFile;
    }

    public function testDataverseFileIsInsertedInDB(): void
    {
        $dataverseFileDAO = new DataverseFileDAO();
        $dataverseFileId = $dataverseFileDAO->insertObject($this->dataverseFile);

        $dataverseFileFromDB = $dataverseFileDAO->getById($dataverseFileId);


        $this->assertEquals($this->dataverseFile, $dataverseFileFromDB);
    }
}

?>