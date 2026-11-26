<?php

import('lib.pkp.tests.DatabaseTestCase');
import('plugins.generic.dataverse.classes.draftDatasetFile.DraftDatasetFileDAO');
import('lib.pkp.classes.db.DAO');
import('lib.pkp.classes.file.TemporaryFile');

class DraftDatasetFileDAOTest extends DatabaseTestCase
{
    private $draftDatasetFile;
    private $draftDatasetFileDAO;

    public function setUp(): void
    {
        parent::setUp();
        $this->draftDatasetFileDAO = new DraftDatasetFileDAO();
        $this->draftDatasetFile = $this->createTestDraftDatasetFile();
        HookRegistry::register('Schema::get::draftDatasetFile', function ($hookname, $params) {
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

    protected function getAffectedTables()
    {
        return array("draft_dataset_files");
    }

    private function getDraftDatasetFileData(): array
    {
        return [
            'submissionId' => 100,
            'userId' => 200,
            'fileId' => 300,
            'fileName' => 'example.pdf'
        ];
    }

    private function createTestDraftDatasetFile(): DraftDatasetFile
    {
        $draftDatasetFile = $this->draftDatasetFileDAO->newDataObject();
        $draftDatasetFile->setAllData($this->getDraftDatasetFileData());

        return $draftDatasetFile;
    }

    public function testDataverseFileIsInsertedInDB(): void
    {
        $draftDatasetFileId = $this->draftDatasetFileDAO->insertObject($this->draftDatasetFile);

        $draftDatasetFileFromDB = $this->draftDatasetFileDAO->getById($draftDatasetFileId);

        $this->assertEquals($this->draftDatasetFile, $draftDatasetFileFromDB);
    }

    public function testAttachedUploadSurvivesRemovalOfItsTemporaryFile(): void
    {
        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        $submission = $submissionDao->newDataObject();
        $submission->setData('contextId', 1);
        $submissionId = $submissionDao->insertObject($submission);
        $draft = $this->createTestDraftDatasetFile();
        $draft->setSubmissionId($submissionId);
        $draft->setFileName('README.txt');
        $storage = new DraftDatasetFileStorage();
        $uploadPath = tempnam(sys_get_temp_dir(), 'dataverse-draft-test-');
        $upload = $this->getMockBuilder(TemporaryFile::class)->onlyMethods(['getFilePath'])->getMock();
        $upload->method('getFilePath')->willReturn($uploadPath);
        $upload->setId($draft->getFileId());
        $upload->setUserId($draft->getUserId());

        try {
            file_put_contents($uploadPath, 'Research data documentation');
            $this->draftDatasetFileDAO->attachUpload($draft, $upload);
            unlink($uploadPath);

            $savedDraft = $this->draftDatasetFileDAO->getById($draft->getId());
            $source = $storage->getSource($savedDraft);
            $this->assertInstanceOf(StoredDraftDatasetSource::class, $source);
            $this->assertSame('Research data documentation', file_get_contents($source->getFilePath()));
            $this->assertSame('README.txt', $source->getOriginalFileName());
            $this->assertSame($draft->getFileId(), (int) $source->getId());

            $this->draftDatasetFileDAO->deleteById($draft->getId());
            $this->assertFileDoesNotExist($source->getFilePath());
            $this->assertNull($this->draftDatasetFileDAO->getById($draft->getId()));

            try {
                $this->draftDatasetFileDAO->attachUpload($draft, $upload);
                $this->fail('An expired upload must not be attached.');
            } catch (DraftDatasetFileStorageException $e) {
                $this->assertSame([], $this->draftDatasetFileDAO->getBySubmissionId($submissionId));
            }
        } finally {
            $this->draftDatasetFileDAO->deleteBySubmissionId($submissionId);
            \Illuminate\Database\Capsule\Manager::table('submissions')->where('submission_id', $submissionId)->delete();
            if (is_file($uploadPath)) {
                unlink($uploadPath);
            }
        }
    }
}
