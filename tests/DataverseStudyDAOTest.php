<?php
import('lib.pkp.tests.DatabaseTestCase');
import('plugins.generic.dataverse.classes.DataverseStudy');
import('plugins.generic.dataverse.classes.DataverseStudyDAO');
import('lib.pkp.classes.db.DAO');

class DataverseStudyDAOTest extends DatabaseTestCase {
    
    private $studyDao;
    private $study;
    private $studyId;
    private $submissionId;
    private $editUri;
    private $editMediaUri;
    private $statementUri;
    private $persistentUri;
    private $dataCitation;

    public function setUp(): void {
        parent::setUp();

        $this->submissionId = 4321;
        $this->editUri = "https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit/study/doi:00.00000/ABC/DFG8HI";
        $this->editMediaUri = "https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit-media/study/doi:00.00000/ABC/DFG8HI";
        $this->statementUri = "https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/statement/study/doi:00.00000/ABC/DFG8HI";
        $this->persistentUri = 'https://doi.org/00.00000/ABC/DFG8HI';
        $this->dataCitation = 'Íris Castanheira, 2021, "The Rise of The Machine Empire", https://doi.org/00.00000/ABC/DFG8HI, Demo Dataverse, DRAFT VERSION';

        $this->study = new DataverseStudy();
        $this->study->setSubmissionId($this->submissionId);
        $this->study->setEditUri($this->editUri);
        $this->study->setEditMediaUri($this->editMediaUri);
        $this->study->setStatementUri($this->statementUri);
        $this->study->setDataCitation($this->dataCitation);
        $this->study->setPersistentUri($this->persistentUri);

        $this->studyDao = new DataverseStudyDAO();
        $this->studyId = $this->studyDao->insertStudy($this->study);
    }

    protected function getAffectedTables() {
		return array('dataverse_studies');
	}

    public function testStudyHasInsertedInDB() : void {
        $returnedStudy = $this->studyDao->getStudy($this->studyId);

        $expectedStudyData = array(
            'submission_id'     =>  $this->study->getSubmissionId(),
            'edit_uri'          =>  $this->study->getEditUri(),
            'edit_media_uri'    =>  $this->study->getEditMediaUri(),
            'statement_uri'     =>  $this->study->getStatementUri(),
            'persistent_uri'    =>  $this->study->getPersistentUri(),
            'data_citation'     =>  $this->study->getDataCitation()
        );

        $resultStudyData = array(
            'submission_id'     =>  $returnedStudy->getSubmissionId(),
            'edit_uri'          =>  $returnedStudy->getEditUri(),
            'edit_media_uri'    =>  $returnedStudy->getEditMediaUri(),
            'statement_uri'     =>  $returnedStudy->getStatementUri(),
            'persistent_uri'    =>  $returnedStudy->getPersistentUri(),
            'data_citation'     =>  $returnedStudy->getDataCitation()
        );

        $this->assertEquals($expectedStudyData, $resultStudyData);
    }

    public function testGetStudyBySubmissionId() : void {
        $returnedStudy = $this->studyDao->getStudyBySubmissionId($this->submissionId);

        $expectedStudyData = array(
            'submission_id'     =>  $this->study->getSubmissionId(),
            'edit_uri'          =>  $this->study->getEditUri(),
            'edit_media_uri'    =>  $this->study->getEditMediaUri(),
            'statement_uri'     =>  $this->study->getStatementUri(),
            'persistent_uri'    =>  $this->study->getPersistentUri(),
            'data_citation'     =>  $this->study->getDataCitation()
        );

        $resultStudyData = array(
            'submission_id'     =>  $returnedStudy->getSubmissionId(),
            'edit_uri'          =>  $returnedStudy->getEditUri(),
            'edit_media_uri'    =>  $returnedStudy->getEditMediaUri(),
            'statement_uri'     =>  $returnedStudy->getStatementUri(),
            'persistent_uri'    =>  $returnedStudy->getPersistentUri(),
            'data_citation'     =>  $returnedStudy->getDataCitation()
        );

        $this->assertEquals($expectedStudyData, $resultStudyData);
    }
}
