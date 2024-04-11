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

    public function get(int $studyId): ?DraftDatasetFile
    {
        return $this->dao->get($studyId);
    }

    public function getBySubmissionId(int $submissionId): LazyCollection
    {
        return $this->dao->getBySubmissionId($submissionId);
    }

    public function getAll(int $contextId): LazyCollection
    {
        return $this->dao->getAll($contextId);
    }

    public function add(DraftDatasetFile $draftDatasetFile): int
    {
        return $this->dao->insert($draftDatasetFile);
    }

    public function delete(DraftDatasetFile $draftDatasetFile)
    {
        $this->dao->deleteById($draftDatasetFile->getId());
    }

    public function deleteBySubmissionId(int $submissionId)
    {
        $this->dao->deleteBySubmissionId($submissionId);
    }
}
