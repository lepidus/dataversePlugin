<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.DatasetModel');

class DatasetModelTest extends PKPTestCase
{
    private $title;
    private $description;
    private $creator = array();
    private $subject = array();
    private $contributor = array();
    private $publisher;
    private $date;
    private $type = array();
    private $source;
    private $relation;
    private $coverage = array();
    private $license;
    private $rights;
    private $isReferencedBy = array();
    private $datasetModel;

    public function setUp(): void
    {
        $this->setRequiredMetadata();
        parent::setUp();
    }

    private function setRequiredMetadata(): void
    {
        $this->title = "The Rise of The Machine Empire";
        $this->description = "An example abstract";
        array_push($this->creator, array("Irís Castanheiras"));
        array_push($this->subject, "Computer and Information Science");
        array_push($this->contributor, array("iris@lepidus.com.br", "Contact"));
    }

    private function setOptionalMetadata(): void
    {
        $this->publisher = 'Lepidus Tecnologia Ltda.';
        $this->date = '2021-07-22';
        array_push($this->type, 'test data');
        $this->source = 'The Guide to SWORD API, Dataverse Project';
        $this->relation = 'Peets, John. 2010. Roasting Coffee at the Coffee Shop. Coffeemill Press';
        array_push($this->coverage, 'South America', 'North America');
        $this->license = 'CC';
        $this->rights = 'BY-NC-ND';
        array_push($this->isReferencedBy, array('Castanheiras, I. (2021). <em>The Rise of The Machine Empire</em>. Preprints da Lepidus', array()));
    }

    public function testMetadataValuesContainsData(): void
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

    public function testAddingOptionalMetadata(): void
    {
        $this->publisher = 'Lepidus Tecnologia Ltda.';
        $this->datasetModel = new DatasetModel($this->title, $this->creator, $this->subject, $this->description, $this->contributor, $this->publisher);
        $datasetModelMetadata = $this->datasetModel->getMetadataValues();

        $this->assertEquals($datasetModelMetadata['publisher'], $this->publisher);
    }

    public function testAllMetadataFields(): void
    {
        $this->setOptionalMetadata();

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
