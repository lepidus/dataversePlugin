<?php

import('lib.pkp.tests.DatabaseTestCase');
import('plugins.generic.dataverse.classes.creators.SubmissionAdapterCreator');

class SubmissionAdapterCreatorTest extends DatabaseTestCase
{
    private $submissionAdapterCreator;
    private $submissionAdapter;

    private $submissionId = 1;
    private $submissionTitle = "The Rise of The Machine Empire";

    public function setUp(): void
    {
        $this->submissionAdapterCreator = new SubmissionAdapterCreator();
        $this->submissionAdapter = $this->submissionAdapterCreator->createSubmissionAdapter($this->submissionId);
        parent::setUp();
    }

    public function testCreatorReturnsSubmissionAdapterObject(): void
    {
        $this->assertTrue($this->submissionAdapter instanceof SubmissionAdapter);
    }
}
