<?php

import('lib.pkp.tests.DatabaseTestCase');
import('plugins.generic.dataverse.classes.study.DataverseStudy');
import('plugins.generic.dataverse.classes.study.DataverseStudyDAO');
import('lib.pkp.classes.db.DAO');

class DataverseStudyDAOTest extends DatabaseTestCase
{
    
    private $studyDao;
    private $study;
    private $studyId;
    private $submissionId;
    private $editUri;
    private $editMediaUri;
    private $statementUri;
    private $persistentUri;
    private $dataCitation;
    private $datasetUrl;

    public function setUp(): void
    {
        parent::setUp();

        $this->submissionId = 4321;
        $this->editUri = "https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit/study/doi:00.00000/ABC/DFG8HI";
        $this->editMediaUri = "https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit-media/study/doi:00.00000/ABC/DFG8HI";
        $this->statementUri = "https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/statement/study/doi:00.00000/ABC/DFG8HI";
        $this->persistentUri = 'https://doi.org/00.00000/ABC/DFG8HI';
        $this->dataCitation = 'Íris Castanheira, 2021, "The Rise of The Machine Empire", https://doi.org/00.00000/ABC/DFG8HI, Demo Dataverse, DRAFT VERSION';
        $this->datasetUrl = 'https://demo.dataverse.org/dataset.xhtml?persistentId=doi:10.70122/FK2/W20QWI';

        $this->study = new DataverseStudy();
        $this->study->setSubmissionId($this->submissionId);
        $this->study->setEditUri($this->editUri);
        $this->study->setEditMediaUri($this->editMediaUri);
        $this->study->setStatementUri($this->statementUri);
        $this->study->setDataCitation($this->dataCitation);
        $this->study->setPersistentUri($this->persistentUri);
        $this->study->setDatasetUrl($this->datasetUrl);

        $this->studyDao = new DataverseStudyDAO();
        $this->studyId = $this->studyDao->insertStudy($this->study);
    }

    protected function getAffectedTables(): array
    {
		return array('dataverse_studies');
	}

    public function testStudyHasInsertedInDB() : void
    {
        $returnedStudy = $this->studyDao->getStudy($this->studyId);
        $this->assertEquals($this->study, $returnedStudy);
    }

    public function testGetStudyBySubmissionId() : void
    {
        $returnedStudy = $this->studyDao->getStudyBySubmissionId($this->submissionId);
        $this->assertEquals($this->study, $returnedStudy);
    }
}
