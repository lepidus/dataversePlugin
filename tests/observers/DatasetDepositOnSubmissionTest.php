<?php

use APP\facades\Repo;
use APP\plugins\generic\dataverse\classes\facades\Repo as DataverseRepo;
use APP\plugins\generic\dataverse\classes\services\DataStatementService;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use APP\plugins\generic\dataverse\tests\helpers\DataverseIntegrationFixture;
use APP\submission\Submission;
use PKP\db\DAORegistry;
use PKP\file\TemporaryFileManager;
use PKP\notification\Notification;
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
        try {
            $this->deleteDatasetsFromDataverse();
        } finally {
            $this->tearDownFixture();
            parent::tearDown();
            $this->restoreCoreSchemas();
        }
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

    public function testSubmissionWithAMissingSourceFileIsNotDepositedAndKeepsItsDraftFiles(): void
    {
        ini_set('error_log', '/dev/null');
        $submission = $this->createSubmissionWithResearchData();
        $draftFiles = DataverseRepo::draftDatasetFile()->getBySubmissionId($submission->getId())->values();
        $missingFileId = $draftFiles->last()->getData('fileId');
        (new TemporaryFileManager())->deleteById($missingFileId, $this->user->getId());
        $this->temporaryFileIds = array_diff($this->temporaryFileIds, [$missingFileId]);

        $this->submit($submission);

        $this->assertNull(DataverseRepo::dataverseStudy()->getBySubmissionId($submission->getId()));
        $this->assertCount(2, DataverseRepo::draftDatasetFile()->getBySubmissionId($submission->getId()));
        $notification = Notification::withUserId($this->user->getId())
            ->withType(Notification::NOTIFICATION_TYPE_ERROR)
            ->latest('notification_id')
            ->first();
        $this->assertNotNull($notification);
        $this->assertEquals(
            __('plugins.generic.dataverse.error.sourceFileMissing'),
            DAORegistry::getDAO('NotificationSettingsDAO')->getNotificationSettings($notification->id)['contents'] ?? null
        );
    }
}
