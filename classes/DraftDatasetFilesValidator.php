<?php

namespace APP\plugins\generic\dataverse\classes;

use PKP\config\Config;
use PKP\file\TemporaryFileManager;
use APP\plugins\generic\dataverse\classes\facades\Repo;

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
                Repo::draftDatasetFile()->delete($file);
                continue;
            }

            $fileName = strtolower($file->getFileName());
            $fileType = $tempFile->getData('filetype');

            if (
                $this->filenameHasReadmeKeyword($fileName)
                && ($fileType == 'application/pdf' || $fileType == 'text/plain')
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
