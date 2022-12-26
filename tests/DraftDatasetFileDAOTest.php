<?php

import('lib.pkp.tests.DatabaseTestCase');
import('plugins.generic.dataverse.classes.file.DraftDatasetFileDAO');
import('lib.pkp.classes.db.DAO');

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
            'submissionId' => 6,
            'userId' => 7,
            'fileId' => 8,
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
}
