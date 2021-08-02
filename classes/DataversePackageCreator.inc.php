<?php

require_once('plugins/generic/dataverse/libs/swordappv2-php-library/packager_atom_twostep.php');

class DataversePackageCreator extends PackagerAtomTwoStep
{
    private $outPath;
    private $fileDir = 'files';
    private $files = array();

    public function DataversePackageCreator()
    {
        // Create temporary directory for Atom entry & deposit files
        $this->outPath = tempnam('/tmp', 'dataverse');
        unlink($this->outPath);
        mkdir($this->outPath);
        mkdir($this->outPath .DIRECTORY_SEPARATOR. $this->fileDir);
        parent::__construct($this->outPath, $this->fileDir, $this->outPath, "");
    }

    public function createAtomEntry(): void
    {
        $this->create();
    }

    public function getAtomEntryPath()
    {
        return $this->outPath . DIRECTORY_SEPARATOR . $this->fileDir . DIRECTORY_SEPARATOR . 'atom';
    }

    public function getOutPath()
    {
        return $this->outPath;
    }

    public function loadMetadataFromDatasetModel(DatasetModel $dataset): void
    {
        $datasetMetadata = $dataset->getMetadataValues();
        foreach ($datasetMetadata as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $innerKey => $metadata) {
                    if ($key == "contributor") {
                        $this->addMetadata($key, $metadata, array("type" => $innerKey));
                    } else {
                        $this->addMetadata($key, $metadata);
                    }
                }
            } else {
                $this->addMetadata($key, $value);
            }
        }
    }
}
