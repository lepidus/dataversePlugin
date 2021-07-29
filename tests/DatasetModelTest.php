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
        parent::setUp();
    }

    public function testValidateDatasetModelTitle(): void
    {
        $this->datasetModel = new DatasetModel($this->title, $this->creator, $this->subject, $this->description, $this->contributor);
        $expectedTitle = $this->title;
        $resultTitle = $this->datasetModel->getTitle();

        self::assertEquals($expectedTitle, $resultTitle);
    }

    public function testValidateDatasetModelCreator(): void
    {
        $this->datasetModel = new DatasetModel($this->title, $this->creator, $this->subject, $this->description, $this->contributor);
        $expectedCreator = $this->creator;
        $resultCreator = $this->datasetModel->getCreator();

        self::assertEquals($expectedCreator, $resultCreator);
    }

    public function testValidateDatasetModelSubject(): void
    {
        $this->datasetModel = new DatasetModel($this->title, $this->creator, $this->subject, $this->description, $this->contributor);
        $expectedSubject = $this->subject;
        $resultSubject = $this->datasetModel->getSubject();

        self::assertEquals($expectedSubject, $resultSubject);
    }

    public function testValidateDatasetModelDescription(): void
    {
        $this->datasetModel = new DatasetModel($this->title, $this->creator, $this->subject, $this->description, $this->contributor);
        $expectedDescription = $this->description;
        $resultDescription = $this->datasetModel->getDescription();

        self::assertEquals($expectedDescription, $resultDescription);
    }

    public function testValidateDatasetModelContributor(): void
    {
        $this->datasetModel = new DatasetModel($this->title, $this->creator, $this->subject, $this->description, $this->contributor);
        $expectedContributor = $this->contributor;
        $resultContributor = $this->datasetModel->getContributor();

        self::assertEquals($expectedContributor, $resultContributor);
    }

    public function testAllValidMetadata(): void
    {
        $this->datasetModel = new DatasetModel($this->title, $this->creator, $this->subject, $this->description, $this->contributor);
        $datasetModelMetadata = $this->datasetModel->getMetadataValues();

        $expectedMetadata = array(
            'title' => $this->title,
            'creator' => $this->creator,
            'subject' => $this->subject,
            'description' => $this->description,
            'contributor' => $this->contributor);



        $this->assertEquals($expectedMetadata, $datasetModelMetadata);
    }

    public function testOptionalMetadata(): void
    {
        $this->publisher = 'Lepidus Tecnologia Ltda.';
        $this->datasetModel = new DatasetModel($this->title, $this->creator, $this->subject, $this->description, $this->contributor, $this->publisher);

        $datasetModelMetadata = $this->datasetModel->getMetadataValues();

        $this->assertEquals($this->datasetModel->getPublisher(), $this->publisher);
    }

    public function testAllMetadataFields(): void
    {
        $this->publisher = 'Lepidus Tecnologia Ltda.';
        $this->date = '2021';
        $this->type = 'test data';
        $this->source = 'The Guide to SWORD API, Dataverse Project';
        $this->relation = 'Peets, John. 2010. Roasting Coffee at the Coffee Shop. Coffeemill Press';
        $this->coverage = 'South America';
        $this->license = 'CC';
        $this->rights = 'BY-NC-ND';
        $this->isReferencedBy = 'None';

        $expectedMetadata = array(
            'title' => $this->title,
            'creator' => $this->creator,
            'subject' => $this->subject,
            'description' => $this->description,
            'contributor' => $this->contributor,
            'publisher' => $this->publisher,
            'date' => $this->date,
            'type' => $this->type,
            'source' => $this->source,
            'relation' => $this->relation,
            'coverage' => $this->coverage,
            'license' => $this->license,
            'rights' => $this->rights,
            'isReferencedBy' => $this->isReferencedBy
        );

        $this->datasetModel = new DatasetModel(
            $this->title,
            $this->creator,
            $this->subject,
            $this->description,
            $this->contributor,
            $this->publisher,
            $this->date,
            $this->type,
            $this->source,
            $this->relation,
            $this->coverage,
            $this->license,
            $this->rights,
            $this->isReferencedBy
        );

        $datasetModelMetadata = $this->datasetModel->getMetadataValues();
        $this->assertEquals($expectedMetadata, $datasetModelMetadata);
    }
}
