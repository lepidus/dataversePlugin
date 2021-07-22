<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.DataversePackageCreator');

class DataversePackageCreatorTest extends PKPTestCase
{
    private $packageCreator;

    public function setUp(): void
    {
        parent::setUp();
    }

    public function testCreateAtomEntryInLocalTempFiles(): void
    {
        $this->packageCreator = new DataversePackageCreator();
        $this->packageCreator->addMetadata('title', 'Test title');
        $this->packageCreator->addMetadata('description', 'Test description');
        $this->packageCreator->addMetadata('creator', 'The tester');
        $this->packageCreator->addMetadata('subject', 'Testing Entries');
        $this->packageCreator->addMetadata('contributor', 'test@lepidus.com.br');
        $this->packageCreator->createAtomEntry();
        $this->assertTrue(true);
    }
}
