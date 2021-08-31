<?php

require_once('plugins/generic/dataverse/libs/swordappv2-php-library/packager_atom_twostep.php');

class DataversePackageCreator extends PackagerAtomTwoStep
{
    private const FILE_DIR = "files";
    private const PACKAGE_FILE_NAME = "deposit.zip";
    private const TEMPORARY_FILES_DIR = "/tmp";
    private const PACKAGING = 'http://purl.org/net/sword/package/SimpleZip';
    private const CONTENT_TYPE = 'application/zip';
    private $outPath;
    private $files = array();

    public function DataversePackageCreator()
    {
        // Create temporary directory for Atom entry & deposit files
        $this->outPath = tempnam(self::TEMPORARY_FILES_DIR, 'dataverse');
        unlink($this->outPath);
        mkdir($this->outPath);
        mkdir($this->outPath .DIRECTORY_SEPARATOR. self::FILE_DIR);
        parent::__construct($this->outPath, self::FILE_DIR, $this->outPath, "");
    }

    public function createAtomEntry(): void
    {
        $this->create();
    }

    public function getAtomEntryPath(): string
    {
        return $this->outPath . DIRECTORY_SEPARATOR . self::FILE_DIR . DIRECTORY_SEPARATOR . 'atom';
    }

    public function getOutPath()
    {
        return $this->outPath;
    }

    public function loadMetadata(DatasetModel $dataset): void
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

    public function createPackage(): void
    {
        $package = new ZipArchive();
        $package->open($this->getPackageFilePath(), ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE);
        foreach ($this->files as $fileName => $filePath) {
            $package->addFile($filePath, $fileName);
        }
        $package->close();
    }

    public function getPackageFilePath(): string
    {
        return $this->outPath . DIRECTORY_SEPARATOR . self::FILE_DIR . DIRECTORY_SEPARATOR . self::PACKAGE_FILE_NAME;
    }

    public function addFileToPackage($filePath, $fileName)
    {
        $this->files[$fileName] = $filePath;
    }

    function getPackaging() {
		return self::PACKAGING;
	}

    function getContentType() {
		return self::CONTENT_TYPE;
	}
}
