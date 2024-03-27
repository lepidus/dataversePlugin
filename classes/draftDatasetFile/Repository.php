<?php

namespace APP\plugins\generic\dataverse\classes\draftDatasetFile;

use Illuminate\Support\LazyCollection;
use APP\plugins\generic\dataverse\classes\draftDatasetFile\DraftDatasetFile;
use APP\plugins\generic\dataverse\classes\draftDatasetFile\DAO;

class Repository
{
    public $dao;

    public function __construct(DAO $dao)
    {
        $this->dao = $dao;
    }

    public function newDataObject(): DraftDatasetFile
    {
        return $this->dao->newDataObject();
    }

    public function getBySubmissionId(int $submissionId): LazyCollection
    {
        return $this->dao->getBySubmissionId($submissionId);
    }

    public function add(DraftDatasetFile $draftDatasetFile): int
    {
        return $this->dao->insert($draftDatasetFile);
    }
}
