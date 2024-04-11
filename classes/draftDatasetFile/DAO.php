<?php

namespace APP\plugins\generic\dataverse\classes\draftDatasetFile;

use PKP\core\EntityDAO;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Facades\DB;
use APP\plugins\generic\dataverse\classes\draftDatasetFile\DraftDatasetFile;

class DAO extends EntityDAO
{
    public $schema = 'draftDatasetFile';
    public $table = 'draft_dataset_files';
    public $primaryKeyColumn = 'draft_dataset_file_id';
    public $primaryTableColumns = [
        'id' => 'draft_dataset_file_id',
        'submissionId' => 'submission_id',
        'userId' => 'user_id',
        'fileId' => 'file_id',
        'fileName' => 'file_name',
    ];

    public function newDataObject(): DraftDatasetFile
    {
        return app(DraftDatasetFile::class);
    }

    public function get(int $id): ?DraftDatasetFile
    {
        $row = DB::table($this->table)
            ->where('draft_dataset_file_id', $id)
            ->first();

        return $row ? $this->fromRow($row) : null;
    }

    public function getBySubmissionId(int $submissionId): LazyCollection
    {
        $rows = DB::table($this->table)
            ->where('submission_id', $submissionId)
            ->get();

        return $this->makeCollectionFromRows($rows);
    }

    public function getAll(int $contextId): LazyCollection
    {
        $rows = DB::table($this->table)
            ->whereIn('submission_id', function ($query) use ($contextId) {
                $query->select('submission_id')
                    ->from('submissions')
                    ->where('context_id', $contextId);
            })
            ->get();

        return $this->makeCollectionFromRows($rows);
    }

    public function insert(DraftDatasetFile $draftDatasetFile): int
    {
        return parent::_insert($draftDatasetFile);
    }

    public function deleteBySubmissionId(int $submissionId)
    {
        DB::table($this->table)
            ->where('submission_id', $submissionId)
            ->delete();
    }

    private function makeCollectionFromRows($rows): LazyCollection
    {
        return LazyCollection::make(function () use ($rows) {
            foreach ($rows as $row) {
                yield $row->draft_dataset_file_id => $this->fromRow($row);
            }
        });
    }

    public function fromRow(object $row): DraftDatasetFile
    {
        return parent::fromRow($row);
    }
}
