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
    private $datasetModel;

    public function setUp(): void
    {
        $this->title = "The Rise of The Machine Empire";
        $this->creator = "Irís Castanheiras";
        $this->subject = "Computer and Information Science";
        $this->description = "An example abstract";
        $this->contributor = "iris@lepidus.com.br";
        $this->datasetModel = new DatasetModel($this->title, $this->creator, $this->subject, $this->description, $this->contributor);
        parent::setUp();
    }

    public function testValidateDatasetModelTitle(): void
    {
        $expectedTitle = $this->title;
        $resultTitle = $this->datasetModel->getTitle();

        self::assertEquals($expectedTitle, $resultTitle);
    }

    public function testValidateDatasetModelCreator(): void
    {
        $expectedCreator = $this->creator;
        $resultCreator = $this->datasetModel->getCreator();

        self::assertEquals($expectedCreator, $resultCreator);
    }

    public function testValidateDatasetModelSubject(): void
    {
        $expectedSubject = $this->subject;
        $resultSubject = $this->datasetModel->getSubject();

        self::assertEquals($expectedSubject, $resultSubject);
    }

    public function testValidateDatasetModelDescription(): void
    {
        $expectedDescription = $this->description;
        $resultDescription = $this->datasetModel->getDescription();

        self::assertEquals($expectedDescription, $resultDescription);
    }

    public function testValidateDatasetModelContributor(): void
    {
        $expectedContributor = $this->contributor;
        $resultContributor = $this->datasetModel->getContributor();

        self::assertEquals($expectedContributor, $resultContributor);
    }

    public function testAllValidMetadata(): void
    {
        $datasetModelMetadata = $this->datasetModel->getMetadataValues();

        $expectedMetadata = array(
            'title' => $this->title,
            'creator' => $this->creator,
            'subject' => $this->subject,
            'description' => $this->description,
            'contributor' => $this->contributor);

        $this->assertEquals($expectedMetadata, $datasetModelMetadata);
    }
}
