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
        $this->authors = array(new AuthorAdapter("Irís", "Castanheiras", "Lepidus", $this->authorsEmails[0]));
        
        $submissionAdapter = new SubmissionAdapter($this->title, $this->authors, $this->description, $this->keywords);
        $datasetBuilder = new DatasetBuilder();
        $this->dataset = $datasetBuilder->build($submissionAdapter);
    }

    public function testBuildDatasetModel(): void
    {
        $this->assertTrue($this->dataset instanceof DatasetModel);
    }

    public function testMetadataValuesContainsSubmissionData()
    {
        foreach ($this->authors as $author)
        {
            $creator[] = $author->getFullName();
            $contributor = array('contact' => $author->getAuthorEmail());
        }
        $expectedData = array(
            'title' => $this->title,
            'description' => $this->description,
            'creator' => $creator,
            'subject' => $this->keywords,
            'contributor' => $contributor,
        );

        $this->assertEquals($expectedData, $this->dataset->getMetadataValues());
    }
}
