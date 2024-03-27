<?php

use PKP\tests\DatabaseTestCase;
use APP\submission\Submission;
use APP\publication\Publication;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\classes\dataverseStudy\DataverseStudy;

class RepositoryTest extends DatabaseTestCase
{
    private $study;
    private $submissionId;
    private $editUri;
    private $editMediaUri;
    private $statementUri;
    private $persistentUri;
    private $persistentId;

    public function setUp(): void
    {
        parent::setUp();

        $this->submissionId = $this->createSubmission();
        $this->editUri = "https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit/study/doi:00.00000/ABC/DFG8HI";
        $this->editMediaUri = "https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/edit-media/study/doi:00.00000/ABC/DFG8HI";
        $this->statementUri = "https://demo.dataverse.org/dvn/api/data-deposit/v1.1/swordv2/statement/study/doi:00.00000/ABC/DFG8HI";
        $this->persistentUri = 'https://doi.org/00.00000/ABC/DFG8HI';
        $this->persistentId = 'https://demo.dataverse.org/dataset.xhtml?persistentId=doi:10.70122/FK2/W20QWI';

        $this->study = $this->createDataverseStudy();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $submission = Repo::submission()->get($this->submissionId);
        Repo::submission()->delete($submission);
    }

    protected function getAffectedTables(): array
    {
        return [...parent::getAffectedTables(), 'dataverse_studies'];
    }

    private function createSubmission(): int
    {
        $contextId = 1;
        $context = DAORegistry::getDAO('JournalDAO')->getById($contextId);

        $submission = new Submission();
        $submission->setData('contextId', $contextId);
        $publication = new Publication();

        return Repo::submission()->add($submission, $publication, $context);
    }

    private function createDataverseStudy(): DataverseStudy
    {
        $study = new DataverseStudy();
        $study->setSubmissionId($this->submissionId);
        $study->setEditUri($this->editUri);
        $study->setEditMediaUri($this->editMediaUri);
        $study->setStatementUri($this->statementUri);
        $study->setPersistentUri($this->persistentUri);
        $study->setPersistentId($this->persistentId);

        $id = Repo::dataverseStudy()->add($study);
        $study->setId($id);

        return $study;
    }

    public function testGetNewDataObject(): void
    {
        $dataverseStudy = Repo::dataverseStudy()->newDataObject();
        $this->assertInstanceOf(DataverseStudy::class, $dataverseStudy);
    }

    public function testGetDataverseStudy(): void
    {
        $dataverseStudy = Repo::dataverseStudy()->get($this->study->getId());
        $this->assertEquals($this->study->getAllData(), $dataverseStudy->getAllData());
    }

    public function testGetBySubmissionId(): void
    {
        $dataverseStudy = Repo::dataverseStudy()->getBySubmissionId($this->submissionId);
        $this->assertEquals($this->study->getAllData(), $dataverseStudy->getAllData());
    }

    public function testGetByPersistentId(): void
    {
        $dataverseStudy = Repo::dataverseStudy()->getByPersistentId($this->study->getPersistentId());
        $this->assertEquals($this->study->getAllData(), $dataverseStudy->getAllData());
    }

    public function testUpdateDataverseStudy(): void
    {
        $newPersistentId = "https://demo.dataverse.org/dataset.xhtml?persistentId=doi:10.40123/FK2/WYSIWYG";
        $newPersistentUri = 'https://doi.org/00.00000/ABC/DFG8HI';
        $this->study->setPersistentId($newPersistentId);

        Repo::dataverseStudy()->edit($this->study, [
            'persistentUri' => $newPersistentUri
        ]);
        $this->study->setPersistentUri($newPersistentUri);

        $dataverseStudy = Repo::dataverseStudy()->get($this->study->getId());
        $this->assertEquals($this->study->getAllData(), $dataverseStudy->getAllData());
    }

    public function testDeleteDataverseStudy(): void
    {
        Repo::dataverseStudy()->delete($this->study);

        $dataverseStudy = Repo::dataverseStudy()->get($this->study->getId());
        $this->assertNull($dataverseStudy);
    }
}
