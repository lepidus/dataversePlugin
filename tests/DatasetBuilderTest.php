<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.creators.DatasetBuilder');

class DatasetBuilderTest extends PKPTestCase
{
    private $datasetBuilder;
    private $dataset;

    private $title = "The Rise of The Machine Empire";
    private $authors;
    private $description = "This is a description/abstract.";
    private $keywords = array("Modern History", "Computer Science");
    private $authorsEmails = array("iris@lepidus.com.br");

    public function setUp(): void
    {
        parent::setUp();
        $this->authors = array(new AuthorAdapter("Irís Castanheiras", "Lepidus", $this->authorsEmails[0]));
        
        $this->datasetBuilder = new DatasetBuilder();
        $submissionAdapter = new SubmissionAdapter($this->title, $this->authors, $this->description, $this->keywords);
        $this->dataset = $this->datasetBuilder->build($submissionAdapter);
    }

    public function testBuildDatasetModel(): void
    {
        $this->assertTrue($this->dataset instanceof DatasetModel);
    }

    public function testValidateData()
    {
        $expectedData = array(
            'title' => $this->title,
            'description' => $this->description,
            'creator' => $this->authors,
            'subject' => $this->keywords,
            'contributor' => $this->authorsEmails
        );

        $this->assertEquals($expectedData, $this->dataset->getMetadataValues());
    }
}
