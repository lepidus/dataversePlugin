<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.DataversePackageCreator');

class DataversePackageCreatorTest extends PKPTestCase
{
    private $packageCreator;

    public function setUp(): void
    {
        $this->packageCreator = new DataversePackageCreator();
        parent::setUp();
    }

    public function tearDown(): void
    {
        if (file_exists($this->packageCreator->getAtomEntryPath())) {
            unlink($this->packageCreator->getAtomEntryPath());
        }
        rmdir($this->packageCreator->getOutPath() . '/files');
        rmdir($this->packageCreator->getOutPath());
        parent::tearDown();
    }

    public function testCreateExampleAtomEntryInLocalTempFiles(): void
    {
        $this->packageCreator->addMetadata('title', 'Example Title');
        $this->packageCreator->addMetadata('description', 'Example description');
        $this->packageCreator->addMetadata('creator', 'Exemple Creator');
        $this->packageCreator->addMetadata('subject', 'Example subject');
        $this->packageCreator->addMetadata('contributor', 'example@lepidus.com.br');
        $this->packageCreator->createAtomEntry();

        $this->assertEquals($this->packageCreator->getOutPath() . '/files/atom', $this->packageCreator->getAtomEntryPath());
        $this->assertTrue(file_exists($this->packageCreator->getAtomEntryPath()));
    }
}
