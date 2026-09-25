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

    private ?string $depositedPersistentId = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFixture();
        $this->configureWithRealDataverse();
        $this->registerPlugin();
    }

    protected function tearDown(): void
    {
        if ($this->depositedPersistentId) {
            (new DataverseClient())->getDatasetActions()->delete($this->depositedPersistentId);
        }
        $this->tearDownFixture();
        parent::tearDown();
        $this->restoreCoreSchemas();
    }

    private function createSubmissionWithResearchData(array $submissionData, bool $withFiles = true): Submission
    {
        $submission = $this->createSubmission(
            [
                'dataStatementTypes' => [DataStatementService::DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED],
                'keywords' => ['en' => ['mass public transport', 'sustainable cities']],
            ],
            array_merge([
                'datasetLanguage' => 'English',
                'datasetLicense' => 'CC0 1.0',
                'datasetRelationType' => 'IsSupplementedBy',
            ], $submissionData)
        );
        $this->addAuthor($submission);

        if ($withFiles) {
            $this->addDraftDatasetFile($submission, 'LEIAME.pdf', 'application/pdf', '%PDF-1.4 readme');
            $this->addDraftDatasetFile($submission, 'Planilha_de_dados.json', 'application/json', '{"values": [1, 2, 3]}');
        }

        return Repo::submission()->get($submission->getId());
    }

    private function submit(Submission $submission): void
    {
        Repo::submission()->submit($submission, $this->context);
    }

    public function testSubmittingDepositsResearchDataInDataverse(): void
    {
        $submission = $this->createSubmissionWithResearchData(['datasetSubject' => 'Earth and Environmental Sciences']);

        $this->submit($submission);

        $study = DataverseRepo::dataverseStudy()->getBySubmissionId($submission->getId());
        $this->assertNotNull($study);
        $this->depositedPersistentId = $study->getPersistentId();

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
        $submission = $this->createSubmissionWithResearchData(['datasetSubject' => 'Earth and Environmental Sciences'], false);

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
