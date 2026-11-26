<?php

use Illuminate\Database\Capsule\Manager as Capsule;

import('lib.pkp.classes.db.SchemaDAO');
import('plugins.generic.dataverse.classes.draftDatasetFile.DraftDatasetFileStorage');
import('plugins.generic.dataverse.classes.draftDatasetFile.DraftDatasetFile');

class DraftDatasetFileDAO extends SchemaDAO
{
    public $schemaName = 'draftDatasetFile';

    public $tableName = 'draft_dataset_files';

    public $primaryKeyColumn = 'draft_dataset_file_id';

    public $primaryTableColumns = [
        'id' => 'draft_dataset_file_id',
        'submissionId' => 'submission_id',
        'userId' => 'user_id',
        'fileId' => 'file_id',
        'fileName' => 'file_name',
    ];

    public function newDataObject()
    {
        return new DraftDatasetFile();
    }

    public function getBySubmissionId($submissionId)
    {
        $queryResults = new DAOResultFactory(
            $this->retrieve(
                'SELECT * FROM draft_dataset_files WHERE submission_id = ?',
                [$submissionId]
            ),
            $this,
            '_fromRow'
        );
        return $this->retrieveFiles($queryResults);
    }

    public function attachUpload(DraftDatasetFile $draft, TemporaryFile $source): void
    {
        $storage = new DraftDatasetFileStorage();
        $storedContext = null;
        try {
            Capsule::connection()->transaction(function () use ($draft, $source, $storage, &$storedContext) {
                $submission = Capsule::table('submissions')
                    ->where('submission_id', $draft->getSubmissionId())->lockForUpdate()->first();
                if (!$submission) {
                    throw new DraftDatasetFileStorageException('The submission no longer exists.');
                }
                $draft->setId($this->insertObject($draft));
                $storage->store($draft, $source, (int) $submission->context_id);
                $storedContext = (int) $submission->context_id;
            });
        } catch (Throwable $error) {
            if ($storedContext !== null) {
                $storage->remove($draft, $storedContext);
            }
            throw $error;
        }
    }

    public function deleteById($objectId)
    {
        $draft = $this->getById($objectId);
        if ($draft !== null) {
            $this->removeDraft($draft);
        }
    }

    public function deleteBySubmissionId($submissionId, ?int $contextId = null)
    {
        foreach ($this->getBySubmissionId($submissionId) as $draft) {
            $this->removeDraft($draft, $contextId);
        }
    }

    private function removeDraft(DraftDatasetFile $draft, ?int $contextId = null): void
    {
        $this->update(
            "DELETE FROM $this->tableName WHERE $this->primaryKeyColumn = ?",
            [(int) $draft->getId()]
        );
        (new DraftDatasetFileStorage())->remove($draft, $contextId);
    }

    public function _fromRow($primaryRow)
    {
        $schemaService = Services::get('schema');
        $schema = $schemaService->get($this->schemaName);

        $object = $this->newDataObject();

        foreach ($this->primaryTableColumns as $propName => $column) {
            if (isset($primaryRow[$column])) {
                $object->setData(
                    $propName,
                    $this->convertFromDb($primaryRow[$column], $schema->properties->{$propName}->type)
                );
            }
        }

        return $object;
    }

    private function retrieveFiles($result)
    {
        $files = [];
        while ($file = $result->next()) {
            $files[] = $file;
        }
        return $files;
    }
}
