<?php

namespace APP\plugins\generic\dataverse\classes;

use PKP\config\Config;
use PKP\file\TemporaryFileManager;

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

    public function datasetHasReadmeFile(array $draftDatasetFiles): bool
    {
        $temporaryFileManager = new TemporaryFileManager();

        foreach ($draftDatasetFiles as $file) {
            $tempFile = $temporaryFileManager->getFile(
                $file->getData('fileId'),
                $file->getData('userId')
            );

            if (is_null($tempFile)) {
                continue;
            }

            $fileName = strtolower($file->getFileName());
            $fileType = $tempFile->getData('filetype');
            $filePath = $tempFile->getFilePath();

            if (
                $this->filenameHasReadmeKeyword($fileName)
                && ($fileType == 'application/pdf' || $fileType == 'text/plain')
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
        $temporaryFileManager = new TemporaryFileManager();
        $researchDataFileValidator = new ResearchDataFileValidator();

        foreach ($draftDatasetFiles as $file) {
            $tempFile = $temporaryFileManager->getFile(
                $file->getData('fileId'),
                $file->getData('userId')
            );

            if (
                !is_null($tempFile)
                && $researchDataFileValidator->containsResearchData($tempFile->getFilePath(), $file->getFileName())
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
