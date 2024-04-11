<?php

use PKP\tests\DatabaseTestCase;
use APP\submission\Submission;
use APP\publication\Publication;
use PKP\plugins\Hook;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\classes\draftDatasetFile\DraftDatasetFile;

class DraftDatasetFileRepositoryTest extends DatabaseTestCase
{
    private $contextId = 1;
    private $draftDatasetFiles;
    private $firstSubmissionId;
    private $secondSubmissionId;

    public function setUp(): void
    {
        parent::setUp();
        $this->addDraftDatasetFileSchema();
        $this->firstSubmissionId = $this->createSubmission();
        $this->secondSubmissionId = $this->createSubmission();

        $firstDraftDatasetFile = $this->createDraftDatasetFile($this->firstSubmissionId, 200, 300, 'example.pdf');
        $secondDraftDatasetFile = $this->createDraftDatasetFile($this->secondSubmissionId, 201, 301, 'dummy.pdf');

        $this->draftDatasetFiles = [$firstDraftDatasetFile, $secondDraftDatasetFile];
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $firstSubmission = Repo::submission()->get($this->firstSubmissionId);
        $secondSubmission = Repo::submission()->get($this->secondSubmissionId);
        Repo::submission()->delete($firstSubmission);
        Repo::submission()->delete($secondSubmission);
    }

    protected function getAffectedTables()
    {
        return ['draft_dataset_files'];
    }

    private function addDraftDatasetFileSchema()
    {
        Hook::add('Schema::get::draftDatasetFile', function ($hookname, $params) {
            $schema = &$params[0];
            $draftDatasetFileSchemaFile = BASE_SYS_DIR . '/plugins/generic/dataverse/schemas/draftDatasetFile.json';

            if (file_exists($draftDatasetFileSchemaFile)) {
                $schema = json_decode(file_get_contents($draftDatasetFileSchemaFile));
                if (!$schema) {
                    fatalError('Schema failed to decode. This usually means it is invalid JSON. Requested: ' . $draftDatasetFileSchemaFile . '. Last JSON error: ' . json_last_error());
                }
            }

            return true;
        });
    }

    private function createSubmission(): int
    {
        $context = DAORegistry::getDAO('JournalDAO')->getById($this->contextId);

        $submission = new Submission();
        $submission->setData('contextId', $this->contextId);
        $publication = new Publication();

        return Repo::submission()->add($submission, $publication, $context);
    }

    private function createDraftDatasetFile($submissionId, $userId, $fileId, $fileName): DraftDatasetFile
    {
        $draftDatasetFile = new DraftDatasetFile();
        $draftDatasetFile->setAllData([
            'submissionId' => $submissionId,
            'userId' => $userId,
            'fileId' => $fileId,
            'fileName' => $fileName
        ]);

        $id = Repo::draftDatasetFile()->add($draftDatasetFile);
        $draftDatasetFile->setId($id);

        return $draftDatasetFile;
    }

    public function testGetNewDataObject(): void
    {
        $draftDatasetFile = Repo::draftDatasetFile()->newDataObject();
        $this->assertInstanceOf(DraftDatasetFile::class, $draftDatasetFile);
    }

    public function testGetById(): void
    {
        $draftDatasetFile = $this->draftDatasetFiles[0];
        $retrievedDatasetFile = Repo::draftDatasetFile()->get($draftDatasetFile->getId());

        $this->assertEquals($draftDatasetFile->getAllData(), $retrievedDatasetFile->getAllData());
    }

    public function testGetBySubmissionId(): void
    {
        $retrievedFiles = Repo::draftDatasetFile()->getBySubmissionId($this->firstSubmissionId)->toArray();
        $draftDatasetFile = $this->draftDatasetFiles[0];
        $draftDatasetFileId = $draftDatasetFile->getId();
        $retrievedDatasetFile = $retrievedFiles[$draftDatasetFileId];

        $this->assertCount(1, $retrievedFiles);
        $this->assertEquals($draftDatasetFile->getAllData(), $retrievedDatasetFile->getAllData());
    }

    public function testGetAll(): void
    {
        $retrievedFiles = Repo::draftDatasetFile()->getAll($this->contextId)->toArray();

        $this->assertCount(2, $retrievedFiles);

        foreach ($this->draftDatasetFiles as $draftDatasetFile) {
            $retrievedDatasetFile = $retrievedFiles[$draftDatasetFile->getId()];
            $this->assertEquals($draftDatasetFile->getAllData(), $retrievedDatasetFile->getAllData());
        }
    }

    //teste delete

    //teste deleteBySubmissionId

}
