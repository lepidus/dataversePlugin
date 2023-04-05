<?php

import('plugins.generic.dataverse.dataverseAPI.packagers.DatasetPackager');
import('plugins.generic.dataverse.dataverseAPI.packagers.creators.AtomPackageCreator');

class SWORDAPIDatasetPackager extends DatasetPackager
{
    private $atomPackager;

    public function __construct(Dataset $dataset)
    {
        $this->atomPackager = new AtomPackageCreator();

        parent::__construct($dataset);
    }

    public function createDatasetPackage(): void
    {
        $this->atomPackager->loadMetadata($this->dataset);
        $this->atomPackager->createAtomEntry();
    }

    public function createFilesPackage(): void
    {
        foreach ($this->dataset->getFiles() as $file) {
            $this->atomPackager->addFileToPackage(
                $file->getPath(),
                $file->getOriginalFileName()
            );
        }
        $this->atomPackager->createPackage();
    }

    public function getPackagePath(): string
    {
        return $this->atomPackager->getAtomEntryPath();
    }

    public function clear(): void
    {
        if (file_exists($this->getPackagePath())) {
            unlink($this->getPackagePath());
        }
        if (file_exists($this->atomPackager->getPackageFilePath())) {
            unlink($this->atomPackager->getPackageFilePath());
        }

        rmdir($this->atomPackager->getOutPath() . '/files');
        rmdir($this->atomPackager->getOutPath());
    }

    public function getAtomPackager(): AtomPackageCreator
    {
        return $this->atomPackager;
    }
}
