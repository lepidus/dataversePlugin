<?php

class DraftDatasetFilesValidator
{
    public function galleyContainsResearchData(array $galleyFiles, array $datasetFiles): bool
    {
        $contains = false;

        foreach ($galleyFiles as $galleyFile) {
            $galleyFilePath = $galleyFile->getFilePath();

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
