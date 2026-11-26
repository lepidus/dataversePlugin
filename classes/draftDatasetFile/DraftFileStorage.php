<?php

namespace APP\plugins\generic\dataverse\classes\draftDatasetFile;

use APP\facades\Repo;
use PKP\config\Config;
use PKP\core\PKPString;
use PKP\file\TemporaryFileManager;
use RuntimeException;

class DraftFileStorage
{
    public function save(DraftDatasetFile $file, int $contextId, string $source): void
    {
        $target = $this->path($file, $contextId);
        $directory = dirname($target);
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create storage for a Dataverse draft file.');
        }

        $directory = realpath($directory);
        if ($directory === false) {
            throw new RuntimeException('Could not find storage for a Dataverse draft file.');
        }
        $staged = @tempnam($directory, 'incoming-');
        if ($staged === false) {
            throw new RuntimeException('Could not stage a Dataverse draft file.');
        }

        try {
            if (dirname($staged) !== $directory || !@copy($source, $staged) || !@rename($staged, $target)) {
                throw new RuntimeException('Could not store a Dataverse draft file.');
            }
        } finally {
            if (is_file($staged)) {
                @unlink($staged);
            }
        }
    }

    /** @return array{path: string, name: string, id: int, mimeType: ?string}|null */
    public function resolve(DraftDatasetFile $file, ?int $contextId): ?array
    {
        if ($contextId !== null && $file->getId()) {
            $path = $this->path($file, $contextId);
            if (is_file($path) && is_readable($path)) {
                return [
                    'path' => $path,
                    'name' => $file->getFileName(),
                    'id' => $file->getFileId(),
                    'mimeType' => PKPString::mime_content_type($path, pathinfo($file->getFileName(), PATHINFO_EXTENSION)),
                ];
            }
        }

        // Drafts created before durable storage still point to an owned PKP temporary file.
        $temporaryFile = (new TemporaryFileManager())->getFile($file->getFileId(), $file->getUserId());
        if (!$temporaryFile) {
            return null;
        }
        $path = $temporaryFile->getFilePath();
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        return [
            'path' => $path,
            'name' => $temporaryFile->getOriginalFileName() ?? $file->getFileName(),
            'id' => $file->getFileId(),
            'mimeType' => $temporaryFile->getData('filetype'),
        ];
    }

    public function remove(DraftDatasetFile $file, int $contextId): void
    {
        $this->removePath($this->path($file, $contextId), $file->getSubmissionId());
    }

    public function removeSubmissionFiles(int $contextId, int $submissionId): void
    {
        $directory = $this->submissionDirectory($contextId, $submissionId);
        if (!is_dir($directory)) {
            return;
        }

        $entries = @scandir($directory);
        if ($entries === false) {
            error_log('Could not list Dataverse draft files for submission ' . $submissionId);
            return;
        }
        foreach ($entries as $entry) {
            if (ctype_digit($entry) || strpos($entry, 'incoming-') === 0) {
                $this->removePath($directory . '/' . $entry, $submissionId);
            }
        }
        if (!@rmdir($directory) && is_dir($directory)) {
            error_log('Could not remove Dataverse draft directory for submission ' . $submissionId);
        }
    }

    private function removePath(string $path, int $submissionId): void
    {
        if ((is_file($path) || is_link($path)) && !@unlink($path) && file_exists($path)) {
            error_log('Could not remove Dataverse draft file for submission ' . $submissionId);
        }
    }

    private function submissionDirectory(int $contextId, int $submissionId): string
    {
        return rtrim(Config::getVar('files', 'files_dir'), '/') . '/'
            . Repo::submissionFile()->getSubmissionDir($contextId, $submissionId) . '/dataverse-drafts';
    }

    private function path(DraftDatasetFile $file, int $contextId): string
    {
        return $this->submissionDirectory($contextId, $file->getSubmissionId()) . '/' . (int) $file->getId();
    }
}
