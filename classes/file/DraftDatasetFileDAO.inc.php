<?php

import('lib.pkp.classes.db.SchemaDAO');
import('plugins.generic.dataverse.classes.file.DraftDatasetFile');

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

    public function getAll()
    {
        $queryResults = new DAOResultFactory($this->retrieve('SELECT * FROM draft_dataset_files'), $this, '_fromRow');
        return $this->retrieveFiles($queryResults);
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

    public function deleteById($objectId)
    {
        $this->update(
            "DELETE FROM $this->tableName WHERE $this->primaryKeyColumn = ?",
            [(int) $objectId]
        );
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
