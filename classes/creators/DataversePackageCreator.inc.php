<?php

require_once('plugins/generic/dataverse/libs/swordappv2-php-library/packager_atom_twostep.php');

define('FILE_DIR', 'files');
define('PACKAGE_FILE_NAME', 'deposit.zip');
define('TEMPORARY_FILES_DIR', '/tmp');
define('PACKAGING', 'http://purl.org/net/sword/package/SimpleZip');
define('CONTENT_TYPE', 'application/zip');

class DataversePackageCreator extends PackagerAtomTwoStep
{
    private string $outPath;
    private array $files = array();

    public function DataversePackageCreator()
    {
        $this->outPath = tempnam(TEMPORARY_FILES_DIR, 'dataverse');
        unlink($this->outPath);
        mkdir($this->outPath);
        mkdir($this->outPath. DIRECTORY_SEPARATOR. FILE_DIR);
        parent::__construct($this->outPath, FILE_DIR, $this->outPath, "");
    }

    public function createAtomEntry(): void
    {
        $this->create();
    }

    public function getAtomEntryPath(): string
    {
        return $this->outPath. DIRECTORY_SEPARATOR. FILE_DIR. DIRECTORY_SEPARATOR. 'atom';
    }

    public function getOutPath(): string
    {
        return $this->outPath;
    }

    public function loadMetadata(DatasetModel $dataset): void
    {
        $datasetMetadata = $dataset->getMetadataValues();
        foreach ($datasetMetadata as $key => $value) {
            if (is_array($value)) {
                if ($key == 'isReferencedBy') {
                    $this->addMetadata($key, $value[0], $value[1]);
                } else {
                    foreach ($value as $innerKey => $metadata) {
                        if ($key == "contributor") {
                            $this->addMetadata($key, $metadata, array("type" => $innerKey));
                        } else {
                            $this->addMetadata($key, $metadata);
                        }
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
        return $this->outPath. DIRECTORY_SEPARATOR. FILE_DIR. DIRECTORY_SEPARATOR. PACKAGE_FILE_NAME;
    }

    public function addFileToPackage(string $filePath, string $fileName)
    {
        $this->files[$fileName] = $filePath;
    }

    function getPackaging(): string
    {
		return PACKAGING;
	}

    function getContentType(): string
    {
		return CONTENT_TYPE;
	}
}
