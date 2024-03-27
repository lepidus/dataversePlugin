<?php

use PKP\tests\DatabaseTestCase;
use APP\submission\Submission;
use APP\publication\Publication;
use PKP\plugins\Hook;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\classes\dataverseStudy\DataverseStudy;

class DraftDatasetFileDAOTest extends DatabaseTestCase
{
    private $draftDatasetFile;
    private $submissionId;

    public function setUp(): void
    {
        parent::setUp();
        $this->submissionId = $this->createSubmission();
        $this->draftDatasetFile = $this->createDraftDatasetFile();
        Hook::add('Schema::get::draftDatasetFile', function ($hookname, $params) {
            $schema = &$params[0];
            $draftDatasetFileSchemaFile = BASE_SYS_DIR . '/plugins/generic/dataverse/schemas/draftDatasetFile.json';

            if (file_exists($draftDatasetFileSchemaFile)) {
                $schema = json_decode(file_get_contents($draftDatasetFileSchemaFile));
                if (!$schema) {
                    fatalError('Schema failed to decode. This usually means it is invalid JSON. Requested: ' . $draftDatasetFileSchemaFile . '. Last JSON error: ' . json_last_error());
                }
            }

            return false;
        });
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $submission = Repo::submission()->get($this->submissionId);
        Repo::submission()->delete($submission);
    }

    protected function getAffectedTables()
    {
        return ['draft_dataset_files'];
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

    private function createDraftDatasetFile(): DraftDatasetFile
    {
        $draftDatasetFile = new DraftDatasetFile();
        $draftDatasetFile->setAllData([
            'submissionId' => $this->submissionId,
            'userId' => 200,
            'fileId' => 300,
            'fileName' => 'example.pdf'
        ]);

        return $draftDatasetFile;
    }

    public function testGetNewDataObject(): void
    {
        $draftDatasetFile = Repo::draftDatasetFile()->newDataObject();
        $this->assertInstanceOf(DraftDatasetFile::class, $draftDatasetFile);
    }

    public function testGetBySubmissionId(): void
    {
        $files = Repo::draftDatasetFile()->getBySubmissionId($this->submissionId);
        $draftDatasetFile = $files->toArray()[0];
        $this->assertEquals($this->draftDatasetFile->getAllData(), $draftDatasetFile->getAllData());
    }

    //teste getall

    //teste delete

    //teste deleteBySubmissionId

}
