<?php
import('lib.pkp.tests.DatabaseTestCase');
import('plugins.generic.dataverse.classes.DataverseStudy');
import('plugins.generic.dataverse.classes.DataverseStudyDAO');
import('lib.pkp.classes.db.DAO');

class DataverseStudyDAOTest extends DatabaseTestCase {
    
    private $studyDao;
    private $study;
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
    }

    protected function getAffectedTables() {
		return array('dataverse_studies');
	}

    public function testStudyDAOIsADataverseStudyDAO() : void {
        $this->assertTrue($this->studyDao instanceof DataverseStudyDAO);
    }

    public function testStudyHasInserted() : void {
        $studyId = $this->studyDao->insertObject($this->study);
        $returnedStudy = $this->studyDao->getStudy($studyId);

        $this->assertEquals($returnedStudy->getSubmissionId(), $this->study->getSubmissionId());
    }
}
