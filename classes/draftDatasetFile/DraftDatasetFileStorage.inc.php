<?php

use Illuminate\Database\Capsule\Manager as Capsule;

import('lib.pkp.classes.file.TemporaryFileManager');
import('lib.pkp.classes.file.PKPFile');

class DraftDatasetFileStorageException extends RuntimeException
{
}

class DraftDatasetFileStorage
{
    public function store(DraftDatasetFile $draft, TemporaryFile $source, int $contextId): void
    {
        $path = $this->path($draft, $contextId);
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new DraftDatasetFileStorageException('Could not create private research data storage.');
        }

        $input = @fopen($source->getFilePath(), 'rb');
        if ($input === false) {
            throw new DraftDatasetFileStorageException('Could not open the research data upload.');
        }

        $staging = @tempnam($directory, '.upload-');
        $output = false;
        try {
            if ($staging !== false && dirname($staging) === realpath($directory)) {
                $output = @fopen($staging, 'wb');
            }
            $stat = fstat($input);
            if (
                $output === false
                || $stat === false
                || @stream_copy_to_stream($input, $output) !== $stat['size']
                || !fflush($output)
                || !@rename($staging, $path)
            ) {
                throw new DraftDatasetFileStorageException('Could not store the research data upload.');
            }
        } finally {
            fclose($input);
            if ($output !== false) {
                fclose($output);
            }
            if ($staging !== false && is_file($staging)) {
                @unlink($staging);
            }
        }
    }

    public function getSource(DraftDatasetFile $draft): ?PKPFile
    {
        if ($draft->getId() && $draft->getData('submissionId')) {
            $contextId = $this->getContextId($draft);
            $path = $contextId === null ? null : $this->path($draft, $contextId);
            if ($path !== null && is_file($path)) {
                if (!is_readable($path)) {
                    return null;
                }
                return new StoredDraftDatasetSource($draft, $path);
            }
        }

        // Existing drafts can still use their original core upload while it exists.
        return (new TemporaryFileManager())->getFile($draft->getFileId(), $draft->getUserId());
    }

    public function remove(DraftDatasetFile $draft, ?int $contextId = null): void
    {
        $contextId = $contextId ?? $this->getContextId($draft);
        if ($contextId === null) {
            return;
        }
        $path = $this->path($draft, $contextId);
        if (is_file($path) && !@unlink($path) && is_file($path)) {
            error_log('Dataverse could not remove stored draft research data for draft ' . (int) $draft->getId());
            return;
        }
        @rmdir(dirname($path));
    }

    private function getContextId(DraftDatasetFile $draft): ?int
    {
        $contextId = Capsule::table('submissions')->where('submission_id', $draft->getSubmissionId())->value('context_id');
        return $contextId === null ? null : (int) $contextId;
    }

    private function path(DraftDatasetFile $draft, int $contextId): string
    {
        $directory = Services::get('submissionFile')->getSubmissionDir($contextId, $draft->getSubmissionId());
        return rtrim(Config::getVar('files', 'files_dir'), '/')
            . '/' . $directory . '/dataverse-drafts/' . (int) $draft->getId();
    }
}

class StoredDraftDatasetSource extends PKPFile
{
    /** @var string */
    private $path;

    public function __construct(DraftDatasetFile $draft, string $path)
    {
        $this->path = $path;
        $this->setId($draft->getFileId());
        $this->setOriginalFileName($draft->getFileName());
        $this->setFileType(PKPString::mime_content_type($path, pathinfo($draft->getFileName(), PATHINFO_EXTENSION)));
    }

    public function getFilePath()
    {
        return $this->path;
    }
}
