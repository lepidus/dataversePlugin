<?php

import('lib.pkp.tests.DatabaseTestCase');
import('plugins.generic.dataverse.classes.draftDatasetFile.DraftDatasetFileDAO');
import('lib.pkp.classes.db.DAO');
import('lib.pkp.classes.file.TemporaryFile');
import('plugins.generic.dataverse.classes.DraftDatasetFilesValidator');

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

    /**
     * @dataProvider draftUploadTypes
     */
    public function testAttachedUploadSurvivesRemovalOfItsTemporaryFile(string $name, string $contents, bool $isReadme): void
    {
        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        $submission = $submissionDao->newDataObject();
        $submission->setData('contextId', 1);
        $submissionId = $submissionDao->insertObject($submission);
        $draft = $this->createTestDraftDatasetFile();
        $draft->setSubmissionId($submissionId);
        $draft->setFileName($name);
        $storage = new DraftDatasetFileStorage();
        $uploadPath = tempnam(sys_get_temp_dir(), 'dataverse-draft-test-');
        $upload = $this->getMockBuilder(TemporaryFile::class)->onlyMethods(['getFilePath'])->getMock();
        $upload->method('getFilePath')->willReturn($uploadPath);
        $upload->setId($draft->getFileId());
        $upload->setUserId($draft->getUserId());

        try {
            file_put_contents($uploadPath, $contents);
            $this->draftDatasetFileDAO->attachUpload($draft, $upload);
            unlink($uploadPath);

            $savedDraft = $this->draftDatasetFileDAO->getById($draft->getId());
            $source = $storage->getSource($savedDraft);
            $this->assertInstanceOf(StoredDraftDatasetSource::class, $source);
            $this->assertSame($contents, file_get_contents($source->getFilePath()));
            $this->assertSame($name, $source->getOriginalFileName());
            $this->assertSame($draft->getFileId(), (int) $source->getId());

            $validator = new DraftDatasetFilesValidator();
            $this->assertSame($isReadme, $validator->datasetHasReadmeFile([$savedDraft]));
            $this->assertSame(!$isReadme, $validator->datasetHasNonReadmeFile([$savedDraft]));

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

    public static function draftUploadTypes(): array
    {
        return [
            'README' => ['README.txt', "Research data documentation.\n", true],
            'research data' => ['data.csv', "sample,value\n1,42\n", false],
        ];
    }
}
