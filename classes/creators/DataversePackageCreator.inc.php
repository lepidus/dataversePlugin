<?php

require_once('plugins/generic/dataverse/libs/swordappv2-php-library/packager_atom_twostep.php');

define('FILE_DIR', 'files');
define('PACKAGE_FILE_NAME', 'deposit.zip');
define('TEMPORARY_FILES_DIR', '/tmp');
define('PACKAGING', 'http://purl.org/net/sword/package/SimpleZip');
define('CONTENT_TYPE', 'application/zip');

class DataversePackageCreator extends PackagerAtomTwoStep
{
    private $outPath;
    private $files = array();

    public function __construct()
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

    public function getPackageFilePath(): string
    {
        return $this->outPath. DIRECTORY_SEPARATOR. FILE_DIR. DIRECTORY_SEPARATOR. PACKAGE_FILE_NAME;
    }

    public function addFileToPackage(string $filePath, string $fileName)
    {
        $this->files[$fileName] = $filePath;
    }

    public function getPackaging(): string
    {
        return PACKAGING;
    }

    public function getContentType(): string
    {
        return CONTENT_TYPE;
    }

    public function hasFiles(): bool
    {
        return !empty($this->files);
    }

    public function loadMetadata(Dataset $dataset): void
    {
        $datasetData = $dataset->getAllData();
        $metadata = $this->prepareMetadata($datasetData);
        foreach ($metadata as $data) {
            $this->addMetadata(
                $data['namespace'],
                $data['value'],
                $data['attributes']
            );
        }
    }

    private function prepareMetadata(array $datasetData): array
    {
        $metadata[] = $this->createMetadata('title', $datasetData['title']);
        $metadata[] = $this->createMetadata('description', $datasetData['description']);
        $metadata[] = $this->createMetadata('isReferencedBy', $datasetData['pubCitation']);

        foreach ($datasetData['authors'] as $author) {
            $metadata[] = $this->createMetadata(
                'creator',
                $author->getName(),
                array('affiliation' => $author->getAffiliation())
            );
        }
        if (!empty($datasetData['keywords'])) {
            foreach ($datasetData['keywords'] as $keyword) {
                $metadata[] = $this->createMetadata('subject', $keyword);
            }
        }

        return $metadata;
    }

    private function createMetadata(string $namespace, string $value, array $attributes = array()): array
    {
        return array(
            'namespace' => $namespace,
            'value' => $value,
            'attributes' => $attributes
        );
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
}
