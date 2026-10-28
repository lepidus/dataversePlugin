<?php

namespace APP\plugins\generic\dataverse\classes;

use PKP\config\Config;
use PKP\file\TemporaryFile;
use PKP\file\TemporaryFileManager;

class DraftDatasetFilesValidator
{
    private const README_FILE_TYPES = ['application/pdf', 'text/plain'];

    private array $temporaryFiles = [];

    public function galleyContainsResearchData(array $galleyFiles, array $datasetFiles): bool
    {
        $filesDir = Config::getVar('files', 'files_dir');

        foreach ($galleyFiles as $galleyFile) {
            $galleyFilePath = $filesDir . '/' . $galleyFile->getData('path');

            foreach (array_filter($datasetFiles) as $datasetFile) {
                $datasetFilePath = $datasetFile->getFilePath();

                if (
                    filesize($galleyFilePath) == filesize($datasetFilePath)
                    && md5_file($galleyFilePath) == md5_file($datasetFilePath)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getTemporaryFiles(array $draftDatasetFiles): array
    {
        return array_map(fn ($draftDatasetFile) => $this->getTemporaryFile($draftDatasetFile), $draftDatasetFiles);
    }

    public function datasetHasMissingFile(array $draftDatasetFiles): bool
    {
        foreach ($draftDatasetFiles as $draftDatasetFile) {
            $temporaryFile = $this->getTemporaryFile($draftDatasetFile);
            if (is_null($temporaryFile) || !is_file($temporaryFile->getFilePath())) {
                return true;
            }
        }

        return false;
    }

    public function datasetHasReadmeFile(array $draftDatasetFiles): bool
    {
        foreach ($draftDatasetFiles as $draftDatasetFile) {
            $temporaryFile = $this->getTemporaryFile($draftDatasetFile);
            if (is_null($temporaryFile)) {
                continue;
            }

            $filePath = $temporaryFile->getFilePath();
            if (
                ResearchDataFileValidator::isReadmeName($draftDatasetFile->getFileName())
                && in_array($temporaryFile->getData('filetype'), self::README_FILE_TYPES)
                && is_file($filePath)
                && is_readable($filePath)
                && filesize($filePath) > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function datasetHasNonReadmeFile(array $draftDatasetFiles): bool
    {
        $researchDataFileValidator = new ResearchDataFileValidator();

        foreach ($draftDatasetFiles as $draftDatasetFile) {
            $temporaryFile = $this->getTemporaryFile($draftDatasetFile);
            if (
                !is_null($temporaryFile)
                && $researchDataFileValidator->containsResearchData($temporaryFile->getFilePath(), $draftDatasetFile->getFileName())
            ) {
                return true;
            }
        }

        return false;
    }

    private function getTemporaryFile($draftDatasetFile): ?TemporaryFile
    {
        $fileId = $draftDatasetFile->getData('fileId');

        if (!array_key_exists($fileId, $this->temporaryFiles)) {
            $this->temporaryFiles[$fileId] = (new TemporaryFileManager())->getFile($fileId, $draftDatasetFile->getData('userId'));
        }

        return $this->temporaryFiles[$fileId];
    }
}
