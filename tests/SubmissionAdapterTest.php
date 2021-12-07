<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.adapters.SubmissionAdapter');
import('plugins.generic.dataverse.classes.adapters.AuthorAdapter');


class SubmissionAdapterTest extends PKPTestCase
{
    private SubmissionAdapter $submission;
    private string $title = "Rethinking linguistic relativity";
    private string $description = "This is a description of a submission.";
    private array $keywords = array("Biological Sciences");
    private array $authors;

    public function setUp(): void
    {
        $this->submission = $this->createSubmissionAdapter();
        parent::setUp();
    }

    private function createSubmissionAdapter(): SubmissionAdapter
    {
        $this->authors = array(new AuthorAdapter("Atila", "Iamarino", "USP", "atila@usp.edu.br"));
        return new SubmissionAdapter($this->title, $this->authors, $this->description, $this->keywords);
    }

    public function testSubmissionTitle(): void
    {
        $this->assertEquals($this->title, $this->submission->getTitle());
    }

    public function testSubmissionDescription(): void
    {
        $this->assertEquals($this->description, $this->submission->getDescription());
    }

    public function testSubmissionKeywords(): void
    {
        $this->assertEquals($this->keywords, $this->submission->getKeywords());
    }

    public function testSubmissionAuthors(): void
    {
        $this->assertEquals($this->authors, $this->submission->getAuthors());
    }
}
