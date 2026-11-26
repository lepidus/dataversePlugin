<?php

import('plugins.generic.dataverse.classes.draftDatasetFile.DraftDatasetFileStorage');
import('plugins.generic.dataverse.classes.ResearchDataFileValidator');

class DraftDatasetFilesValidator
{
    public function galleyContainsResearchData(array $galleyFiles, array $datasetFiles): bool
    {
        $filesDir = Config::getVar('files', 'files_dir');
        $contains = false;

        foreach ($galleyFiles as $galleyFile) {
            $galleyFilePath = $filesDir . '/' . $galleyFile->getData('path');

            foreach ($datasetFiles as $datasetFile) {
                $datasetFilePath = $datasetFile->getFilePath();

                if (
                    filesize($galleyFilePath) == filesize($datasetFilePath)
                    && md5_file($galleyFilePath) == md5_file($datasetFilePath)
                ) {
                    $contains = true;
                    break;
                }
            }
        }

        return $contains;
    }

    public function getMissingFileNames(array $draftDatasetFiles): array
    {
        $storage = new DraftDatasetFileStorage();
        $missing = [];
        foreach ($draftDatasetFiles as $draft) {
            $source = $storage->getSource($draft);
            if ($source === null || !is_file($source->getFilePath()) || !is_readable($source->getFilePath())) {
                $missing[] = $draft->getFileName();
            }
        }
        return $missing;
    }

    public function datasetHasReadmeFile(array $datasetFiles): bool
    {
        $storage = new DraftDatasetFileStorage();

        foreach ($datasetFiles as $file) {
            $tempFile = $storage->getSource($file);

            if (is_null($tempFile)) {
                continue;
            }

            $fileName = strtolower($file->getFileName());
            $fileType = $tempFile->getData('filetype');

            if (
                $this->filenameHasReadmeKeyword($fileName)
                && ($fileType == 'application/pdf' || $fileType == 'text/plain')
                && is_file($tempFile->getFilePath())
                && is_readable($tempFile->getFilePath())
                && filesize($tempFile->getFilePath()) > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function datasetHasNonReadmeFile(array $datasetFiles): bool
    {
        $storage = new DraftDatasetFileStorage();
        $validator = new ResearchDataFileValidator();

        foreach ($datasetFiles as $file) {
            $tempFile = $storage->getSource($file);

            if (
                $tempFile !== null
                && $validator->containsResearchData($tempFile->getFilePath(), $file->getFileName())
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
            if (strpos($fileName, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }
}
