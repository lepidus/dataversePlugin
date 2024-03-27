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

    public function insert(DraftDatasetFile $draftDatasetFile): int
    {
        return parent::_insert($draftDatasetFile);
    }

    public function getBySubmissionId(int $submissionId): LazyCollection
    {
        $rows = DB::table($this->table)
            ->where('submission_id', $submissionId)
            ->get();

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
