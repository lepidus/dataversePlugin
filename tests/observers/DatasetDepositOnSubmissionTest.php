<?php

use APP\facades\Repo;
use APP\plugins\generic\dataverse\classes\facades\Repo as DataverseRepo;
use APP\plugins\generic\dataverse\classes\services\DataStatementService;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use APP\plugins\generic\dataverse\tests\helpers\DataverseIntegrationFixture;
use APP\submission\Submission;
use PKP\tests\DatabaseTestCase;

class DatasetDepositOnSubmissionTest extends DatabaseTestCase
{
    use DataverseIntegrationFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFixture();
        $this->configureWithRealDataverse();
        $this->registerPlugin();
    }

    protected function tearDown(): void
    {
        $this->deleteDatasetsFromDataverse();
        $this->tearDownFixture();
        parent::tearDown();
        $this->restoreCoreSchemas();
    }

    private function submit(Submission $submission): void
    {
        Repo::submission()->submit($submission, $this->context);
    }

    public function testSubmittingDepositsResearchDataInDataverse(): void
    {
        $submission = $this->createSubmissionWithResearchData();

        $this->submit($submission);

        $study = DataverseRepo::dataverseStudy()->getBySubmissionId($submission->getId());
        $this->assertNotNull($study);

        $depositedFiles = (new DataverseClient())->getDatasetFileActions()->getByDatasetId($study->getPersistentId());
        $depositedFileNames = array_map(fn ($file) => $file->getFileName(), $depositedFiles);
        sort($depositedFileNames);
        $this->assertEquals(['LEIAME.pdf', 'Planilha_de_dados.json'], $depositedFileNames);
        $this->assertCount(0, DataverseRepo::draftDatasetFile()->getBySubmissionId($submission->getId()));
    }

    public function testSubmissionWithoutDatasetSubjectIsNotDeposited(): void
    {
        $submission = $this->createSubmissionWithResearchData([]);

        $this->submit($submission);

        $this->assertNull(DataverseRepo::dataverseStudy()->getBySubmissionId($submission->getId()));
    }

    public function testSubmissionWithoutResearchDataFilesIsNotDeposited(): void
    {
        $submission = $this->createSubmissionWithResearchData(withFiles: false);

        $this->submit($submission);

        $this->assertNull(DataverseRepo::dataverseStudy()->getBySubmissionId($submission->getId()));
    }

    public function testSubmissionNotDepositingInDataverseIsNotDeposited(): void
    {
        $submission = $this->createSubmission([
            'dataStatementTypes' => [DataStatementService::DATA_STATEMENT_TYPE_IN_MANUSCRIPT],
        ]);

        $this->submit($submission);

        $this->assertNull(DataverseRepo::dataverseStudy()->getBySubmissionId($submission->getId()));
    }
}
