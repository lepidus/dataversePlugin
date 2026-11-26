<?php

namespace APP\plugins\generic\dataverse\classes\draftDatasetFile;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use RuntimeException;
use Throwable;
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

    public function addFromTemporaryFile(DraftDatasetFile $file, string $source): void
    {
        $storage = new DraftFileStorage();
        $contextId = null;
        try {
            DB::transaction(function () use ($file, $source, $storage, &$contextId) {
                $submission = DB::table('submissions')
                    ->where('submission_id', $file->getSubmissionId())
                    ->lockForUpdate()
                    ->first();
                if (!$submission) {
                    throw new RuntimeException('The submission no longer exists.');
                }
                $contextId = (int) $submission->context_id;
                $file->setId($this->add($file));
                $storage->save($file, $contextId, $source);
            });
        } catch (Throwable $e) {
            if ($contextId !== null && $file->getId()) {
                $storage->remove($file, $contextId);
            }
            throw $e;
        }
    }

    public function delete(DraftDatasetFile $draftDatasetFile)
    {
        $contextId = DB::table('submissions')
            ->where('submission_id', $draftDatasetFile->getSubmissionId())
            ->value('context_id');
        $this->dao->deleteById($draftDatasetFile->getId());
        if ($contextId !== null) {
            (new DraftFileStorage())->remove($draftDatasetFile, (int) $contextId);
        }
    }

    public function deleteBySubmissionId(int $submissionId)
    {
        foreach ($this->getBySubmissionId($submissionId) as $file) {
            $this->delete($file);
        }
    }
}
