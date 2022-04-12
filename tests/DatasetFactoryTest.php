<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.creators.DatasetFactory');
import('plugins.generic.dataverse.classes.adapters.AuthorAdapter');
import('plugins.generic.dataverse.classes.adapters.SubmissionAdapter');
import('plugins.generic.dataverse.classes.adapters.SubmissionFileAdapter');

class DatasetFactoryTest extends PKPTestCase
{
    private $datasetFactory;
    private $dataset;

    private $id = 1;
    private $title = "The Rise of The Machine Empire";
    private $authors;
    private $files;
    private $description = "This is a description/abstract.";
    private $keywords = array("Modern History", "Computer Science");
    private $contributor = array(array('Funder' => 'CAPES'));
    private $authorsEmails = array("iris@lepidus.com.br");

    public function setUp(): void
    {
        parent::setUp();
        $this->authors = array(new AuthorAdapter("Irís", "Castanheiras", "Lepidus", $this->authorsEmails[0]));
        $this->files = array(new SubmissionFileAdapter(1, 'testSample', 'path/to/file', true, 'CAPES'));
        
        $submissionAdapter = new SubmissionAdapter($this->id, $this->title, $this->authors, $this->files, $this->description, $this->keywords);
        $datasetFactory = new DatasetFactory();
        $this->dataset = $datasetFactory->build($submissionAdapter);
    }

    public function testBuildDatasetModel(): void
    {
        $this->assertTrue($this->dataset instanceof DatasetModel);
    }

    public function testMetadataValuesContainsSubmissionData(): void
    {
        foreach ($this->authors as $author)
        {
            $creator[] = $author->getFullName();
        }

        $expectedData = array(
            'title' => $this->title,
            'description' => $this->description,
            'creator' => $creator,
            'subject' => $this->keywords,
            'contributor' => $this->contributor
        );

        $this->assertEquals($expectedData, $this->dataset->getMetadataValues());
    }
}
