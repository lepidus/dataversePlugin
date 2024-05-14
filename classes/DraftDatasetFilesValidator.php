<?php

namespace APP\plugins\generic\dataverse\classes;

use PKP\config\Config;

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
}
