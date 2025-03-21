<?php

import('lib.pkp.classes.file.TemporaryFileManager');

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

    public function datasetHasReadmeFile(array $datasetFiles): bool
    {
        $draftDatasetFileDAO = DAORegistry::getDAO('DraftDatasetFileDAO');
        $temporaryFileManager = new TemporaryFileManager();

        foreach ($datasetFiles as $file) {
            $tempFile = $temporaryFileManager->getFile(
                $file->getData('fileId'),
                $file->getData('userId')
            );

            if (is_null($tempFile)) {
                $draftDatasetFileDAO->deleteById($file->getId());
                continue;
            }

            $fileName = strtolower($file->getFileName());
            $fileType = $tempFile->getData('filetype');

            if (str_contains($fileName, 'readme')
                && ($fileType == 'application/pdf' || $fileType == 'text/plain')
            ) {
                return true;
            }
        }

        return false;
    }
}
