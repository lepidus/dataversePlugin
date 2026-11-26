<?php

namespace APP\plugins\generic\dataverse\classes\draftDatasetFile;

use APP\plugins\generic\dataverse\classes\facades\Repo;
use PKP\config\Config;
use PKP\file\PKPFile;
use PKP\file\FileManager;
use PKP\file\TemporaryFile;
use PKP\file\TemporaryFileManager;
use PKP\core\PKPString;
use RuntimeException;

class DraftDatasetFileStorage
{
    public function store(DraftDatasetFile $draft, int $contextId, TemporaryFile $temporaryFile): void
    {
        $source = @fopen($temporaryFile->getFilePath(), 'rb');
        if ($source === false) {
            throw new RuntimeException('Could not open the uploaded research file.');
        }

        try {
            $directory = $this->getDirectory($contextId, $draft->getSubmissionId());
            if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new RuntimeException('Could not create the research draft directory.');
            }

            $path = $directory . '/' . $draft->getId();
            // Only this request may create the copy. A transaction keeps the row
            // invisible to readers until the file has been written and closed.
            $destination = @fopen($path, 'xb');
            if ($destination === false) {
                throw new RuntimeException('Could not create the research draft file.');
            }
            $copied = false;
            try {
                $expectedSize = fstat($source)['size'] ?? null;
                $copiedSize = @stream_copy_to_stream($source, $destination);
                $copied = $expectedSize !== null && $copiedSize === $expectedSize && @fflush($destination);
            } finally {
                $closed = fclose($destination);
                if (!$copied || !$closed) {
                    @unlink($path);
                }
            }
            if (!$copied || !$closed) {
                throw new RuntimeException('Could not copy the complete research draft file.');
            }
        } finally {
            fclose($source);
        }
    }

    public function getFile(DraftDatasetFile $draft, ?int $contextId = null): ?PKPFile
    {
        $contextId ??= Repo::submission()->get($draft->getSubmissionId())?->getData('contextId');
        if ($contextId !== null) {
            $path = $this->getDirectory($contextId, $draft->getSubmissionId()) . '/' . $draft->getId();
            if (is_file($path) && is_readable($path)) {
                $file = new StoredDraftDatasetFile($path);
                $file->setId($draft->getFileId());
                $file->setOriginalFileName($draft->getFileName());
                $file->setFileType(PKPString::mime_content_type($path, pathinfo($draft->getFileName(), PATHINFO_EXTENSION)));

                return $file;
            }
        }

        // Drafts created before this change still refer to the core temporary upload.
        return (new TemporaryFileManager())->getFile($draft->getFileId(), $draft->getUserId());
    }

    public function delete(DraftDatasetFile $draft, int $contextId): void
    {
        $directory = $this->getDirectory($contextId, $draft->getSubmissionId());
        $path = $directory . '/' . $draft->getId();
        if (is_file($path) && !@unlink($path)) {
            error_log('Dataverse could not remove private draft file ' . $draft->getId());
        }
        if (is_dir($directory)) {
            @rmdir($directory);
        }
    }

    public function deleteSubmission(int $contextId, int $submissionId): void
    {
        $directory = $this->getDirectory($contextId, $submissionId);
        if (is_dir($directory) && !(new FileManager())->rmtree($directory)) {
            error_log('Dataverse could not remove private draft directory for submission ' . $submissionId);
        }
    }

    private function getDirectory(int $contextId, int $submissionId): string
    {
        return rtrim(Config::getVar('files', 'files_dir'), '/')
            . '/' . Repo::submissionFile()->getSubmissionDir($contextId, $submissionId) . '/dataverse-drafts';
    }
}
