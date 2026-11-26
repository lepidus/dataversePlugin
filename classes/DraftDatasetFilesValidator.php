<?php

namespace APP\plugins\generic\dataverse\classes;

use PKP\config\Config;
use PKP\file\PKPFile;
use APP\plugins\generic\dataverse\classes\draftDatasetFile\DraftDatasetFileStorage;

class DraftDatasetFilesValidator
{
    private const README_FILE_TYPES = ['application/pdf', 'text/plain'];

    private array $sourceFiles = [];

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

    public function getSourceFiles(array $draftDatasetFiles): array
    {
        return array_map(fn ($draftDatasetFile) => $this->getSourceFile($draftDatasetFile), $draftDatasetFiles);
    }

    public function getMissingFileNames(array $draftDatasetFiles): array
    {
        $missingFileNames = [];

        foreach ($draftDatasetFiles as $draftDatasetFile) {
            $temporaryFile = $this->getSourceFile($draftDatasetFile);
            if (is_null($temporaryFile) || !is_file($temporaryFile->getFilePath())) {
                $missingFileNames[] = $draftDatasetFile->getFileName();
            }
        }

        return $missingFileNames;
    }

    public function datasetHasReadmeFile(array $draftDatasetFiles): bool
    {
        foreach ($draftDatasetFiles as $draftDatasetFile) {
            $temporaryFile = $this->getSourceFile($draftDatasetFile);
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
            $temporaryFile = $this->getSourceFile($draftDatasetFile);
            if (
                !is_null($temporaryFile)
                && $researchDataFileValidator->containsResearchData($temporaryFile->getFilePath(), $draftDatasetFile->getFileName())
            ) {
                return true;
            }
        }

        return false;
    }

    private function getSourceFile($draftDatasetFile): ?PKPFile
    {
        $draftId = $draftDatasetFile->getId();

        if (!array_key_exists($draftId, $this->sourceFiles)) {
            $this->sourceFiles[$draftId] = (new DraftDatasetFileStorage())->getFile($draftDatasetFile);
        }

        return $this->sourceFiles[$draftId];
    }
}
