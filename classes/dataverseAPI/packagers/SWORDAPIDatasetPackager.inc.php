<?php

import('plugins.generic.dataverse.classes.dataverseAPI.packagers.DatasetPackager');
import('plugins.generic.dataverse.classes.dataverseAPI.packagers.creators.AtomPackageCreator');

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

    public function getAtomPackager(): AtomPackageCreator
    {
        return $this->atomPackager;
    }
}
