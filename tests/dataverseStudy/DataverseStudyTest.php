<?php

use PKP\tests\PKPTestCase;
use APP\plugins\generic\dataverse\classes\dataverseStudy\DataverseStudy;

final class DataverseStudyTest extends PKPTestCase
{
    private $study;
    private $studyId;
    private $submissionId;
    private $editUri;
    private $editMediaUri;
    private $statementUri;
    private $persistentUri;
    private $dataCitation;
    private $persistentId;

    public function setUp(): void
    {
        $this->studyId = 1234;
        $this->submissionId = 4321;
        $this->editUri = "https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit/study/doi:00.00000/ABC/DFG8HI";
        $this->editMediaUri = "https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit-media/study/doi:00.00000/ABC/DFG8HI";
        $this->statementUri = "https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/statement/study/doi:00.00000/ABC/DFG8HI";
        $this->persistentUri = 'https://doi.org/00.00000/ABC/DFG8HI';
        $this->dataCitation = 'Íris Castanheira, 2021, "The Rise of The Machine Empire", https://doi.org/00.00000/ABC/DFG8HI, Demo Dataverse, DRAFT VERSION';
        $this->persistentId = 'https://demo.dataverse.org/dataset.xhtml?persistentId=doi:10.70122/FK2/W20QWI';

        $this->study = new DataverseStudy();
        $this->study->setId($this->studyId);
        $this->study->setSubmissionId($this->submissionId);
        $this->study->setEditUri($this->editUri);
        $this->study->setEditMediaUri($this->editMediaUri);
        $this->study->setStatementUri($this->statementUri);
        $this->study->setDataCitation($this->dataCitation);
        $this->study->setPersistentUri($this->persistentUri);
        $this->study->setPersistentId($this->persistentId);
    }

    public function testDataverseStudyHasId(): void
    {
        $this->assertEquals($this->studyId, $this->study->getId());
    }

    public function testDataverseStudyHasSubmissionId(): void
    {
        $this->assertEquals($this->submissionId, $this->study->getSubmissionId());
    }

    public function testDataverseStudyHasEditUri(): void
    {
        $this->assertEquals($this->editUri, $this->study->getEditUri());
    }

    public function testDataverseStudyHasEditMediaUri(): void
    {
        $this->assertEquals($this->editMediaUri, $this->study->getEditMediaUri());
    }

    public function testDataverseStudyHasStatementUri(): void
    {
        $this->assertEquals($this->statementUri, $this->study->getStatementUri());
    }

    public function testDataverseStudyHasDataCitation(): void
    {
        $this->assertEquals($this->dataCitation, $this->study->getDataCitation());
    }

    public function testDataverseStudyHasPersistentUri(): void
    {
        $this->assertEquals($this->persistentUri, $this->study->getPersistentUri());
    }

    public function testPersistentId(): void
    {
        $this->assertEquals($this->persistentId, $this->study->getPersistentId());
    }
}
