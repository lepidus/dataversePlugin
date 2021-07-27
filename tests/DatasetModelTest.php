<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.DatasetModel');

class DatasetModelTest extends PKPTestCase
{
    private $title;
    private $creator;
    private $subject;
    private $description;
    private $contributor;
    private $publisher;
    private $date;
    private $type;
    private $source;
    private $relation;
    private $coverage;
    private $license;
    private $rights;
    private $isReferencedBy;
    private $dataset;

    public function setUp(): void
    {
        $this->title = "The Rise of The Machine Empire";
        $this->creator = "Irís Castanheiras";
        $this->subject = "Computer and Information Science";
        $this->description = "An example abstract";
        $this->contributor = "iris@lepidus.com.br";
        $this->dataset = new DatasetModel($this->title, $this->creator, $this->subject, $this->description, null, $this->contributor, null, null, null, null, null, null,null, null);
        parent::setUp();
    }

    public function testValidateDatasetTitle() : void
    {
        $expectedTitle = $this->title;
        $resultTitle = $this->dataset->getTitle();

        self::assertEquals($expectedTitle, $resultTitle);
    }

    public function testValidateDatasetCreator() : void
    {
        $expectedCreator = $this->creator;
        $resultCreator = $this->dataset->getCreator();

        self::assertEquals($expectedCreator, $resultCreator);
    }
    
    public function testValidateDatasetSubject() : void
    {
        $expectedSubject = $this->subject;
        $resultSubject = $this->dataset->getSubject();

        self::assertEquals($expectedSubject, $resultSubject);
    }

    public function testValidateDatasetDescription() : void
    {
        $expectedDescription = $this->description;
        $resultDescription = $this->dataset->getDescription();

        self::assertEquals($expectedDescription, $resultDescription);
    }

    public function testValidateDatasetContributor() : void
    {
        $expectedContributor = $this->contributor;
        $resultContributor = $this->dataset->getContributor();

        self::assertEquals($expectedContributor, $resultContributor);
    }
}
