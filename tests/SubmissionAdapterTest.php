<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.adapters.SubmissionAdapter');
import('plugins.generic.dataverse.classes.adapters.SubmissionFileAdapter');
import('plugins.generic.dataverse.classes.adapters.AuthorAdapter');


class SubmissionAdapterTest extends PKPTestCase
{
    private $submission;
    private $id = 1;
    private $title = "Rethinking linguistic relativity";
    private $description = "This is a description of a submission.";
    private $keywords = array("Biological Sciences");
    private $authors;
    private $files;

    public function setUp(): void
    {
        $this->submission = $this->createSubmissionAdapter();
        parent::setUp();
    }

    private function createSubmissionAdapter(): SubmissionAdapter
    {
        $this->authors = array(new AuthorAdapter("Atila", "Iamarino", "USP", "atila@usp.edu.br"));
        $this->files = array(new SubmissionFileAdapter(7, 'sampleTest', 'path/to/file', true, 'CAPES'));
        return new SubmissionAdapter($this->id, $this->title, $this->authors, $this->files, $this->description, $this->keywords);
    }

    public function testHasSubmissionId(): void
    {
        $this->assertEquals($this->id, $this->submission->getId());
    }

    public function testHasSubmissionTitle(): void
    {
        $this->assertEquals($this->title, $this->submission->getTitle());
    }

    public function testHasSubmissionDescription(): void
    {
        $this->assertEquals($this->description, $this->submission->getDescription());
    }

    public function testHasSubmissionKeywords(): void
    {
        $this->assertEquals($this->keywords, $this->submission->getKeywords());
    }

    public function testHasSubmissionAuthors(): void
    {
        $this->assertEquals($this->authors, $this->submission->getAuthors());
    }

    public function testHasSubmissionFiles(): void
    {
        $this->assertEquals($this->files, $this->submission->getFiles());
    }
}
