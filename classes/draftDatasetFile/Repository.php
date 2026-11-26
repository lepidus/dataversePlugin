<?php

namespace APP\plugins\generic\dataverse\classes\draftDatasetFile;

use Illuminate\Support\LazyCollection;
use Illuminate\Support\Facades\DB;
use APP\submission\Submission;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use PKP\file\TemporaryFile;
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

    public function addFromUpload(DraftDatasetFile $draftDatasetFile, Submission $submission, TemporaryFile $temporaryFile): int
    {
        if (
            $draftDatasetFile->getSubmissionId() !== $submission->getId()
            || $draftDatasetFile->getFileId() !== $temporaryFile->getId()
            || $draftDatasetFile->getUserId() !== (int) $temporaryFile->getUserId()
        ) {
            throw new RuntimeException('The research draft does not match the uploaded file.');
        }

        $storage = new DraftDatasetFileStorage();
        $contextId = $submission->getData('contextId');
        $stored = false;

        try {
            return DB::transaction(function () use ($draftDatasetFile, $submission, $temporaryFile, $storage, $contextId, &$stored) {
                $submissionExists = DB::table('submissions')
                    ->where('submission_id', $submission->getId())
                    ->where('context_id', $contextId)
                    ->lockForUpdate()
                    ->first();
                if ($submissionExists === null) {
                    throw new RuntimeException('The submission does not exist.');
                }

                $id = $this->add($draftDatasetFile);
                $draftDatasetFile->setId($id);
                $storage->store($draftDatasetFile, $contextId, $temporaryFile);
                $stored = true;

                return $id;
            });
        } catch (Throwable $error) {
            if ($stored) {
                $storage->delete($draftDatasetFile, $contextId);
            }

            throw $error;
        }
    }

    public function delete(DraftDatasetFile $draftDatasetFile)
    {
        $contextId = Repo::submission()->get($draftDatasetFile->getSubmissionId())?->getData('contextId');
        $this->dao->deleteById($draftDatasetFile->getId());

        if ($contextId !== null) {
            (new DraftDatasetFileStorage())->delete($draftDatasetFile, $contextId);
        }
    }

    public function deleteBySubmissionId(int $submissionId)
    {
        foreach ($this->getBySubmissionId($submissionId) as $file) {
            $this->delete($file);
        }
    }
}
