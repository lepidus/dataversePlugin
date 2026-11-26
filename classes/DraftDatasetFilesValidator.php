<?php

namespace APP\plugins\generic\dataverse\classes;

use PKP\config\Config;
use APP\plugins\generic\dataverse\classes\draftDatasetFile\DraftFileStorage;

class DraftDatasetFilesValidator
{
    public function galleyContainsResearchData(array $galleyFiles, array $datasetFilePaths): bool
    {
        $filesDir = Config::getVar('files', 'files_dir');
        $contains = false;

        foreach ($galleyFiles as $galleyFile) {
            $galleyFilePath = $filesDir . '/' . $galleyFile->getData('path');

            foreach ($datasetFilePaths as $datasetFilePath) {

                if (
                    is_file($galleyFilePath) && is_file($datasetFilePath)
                    && is_readable($galleyFilePath) && is_readable($datasetFilePath)
                    && filesize($galleyFilePath) == filesize($datasetFilePath)
                    && md5_file($galleyFilePath) == md5_file($datasetFilePath)
                ) {
                    $contains = true;
                    break;
                }
            }
        }

        return $contains;
    }

    public function getMissingFileNames(array $draftDatasetFiles, int $contextId): array
    {
        $storage = new DraftFileStorage();
        $missing = [];
        foreach ($draftDatasetFiles as $draft) {
            if ($storage->resolve($draft, $contextId) === null) {
                $missing[] = $draft->getFileName();
            }
        }
        return $missing;
    }

    public function datasetHasReadmeFile(array $draftDatasetFiles, ?int $contextId = null): bool
    {
        $storage = new DraftFileStorage();

        foreach ($draftDatasetFiles as $file) {
            $source = $storage->resolve($file, $contextId);

            if ($source === null) {
                continue;
            }

            $fileName = strtolower($file->getFileName());
            $fileType = $source['mimeType'];

            if (
                $this->filenameHasReadmeKeyword($fileName)
                && ($fileType == 'application/pdf' || $fileType == 'text/plain')
                && is_file($source['path'])
                && is_readable($source['path'])
                && filesize($source['path']) > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function datasetHasNonReadmeFile(array $datasetFiles, ?int $contextId = null): bool
    {
        $storage = new DraftFileStorage();
        $validator = new ResearchDataFileValidator();

        foreach ($datasetFiles as $file) {
            $source = $storage->resolve($file, $contextId);

            if (
                $source !== null
                && $validator->containsResearchData($source['path'], $file->getFileName())
            ) {
                return true;
            }
        }

        return false;
    }

    private function filenameHasReadmeKeyword(string $fileName): bool
    {
        $readmeKeywords = ['readme', 'leiame', 'leia-me', 'leame'];

        foreach ($readmeKeywords as $keyword) {
            if (str_contains($fileName, $keyword)) {
                return true;
            }
        }
        return false;
    }
}
