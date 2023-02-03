<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.adapters.AuthorAdapter');
import('plugins.generic.dataverse.classes.adapters.SubmissionAdapter');
import('plugins.generic.dataverse.classes.entities.DatasetContact');
import('plugins.generic.dataverse.classes.file.DraftDatasetFile');
import('plugins.generic.dataverse.classes.factories.dataset.SubmissionDatasetFactory');

class SubmissionDatasetFactoryTest extends PKPTestCase
{
    private $submission;
    private $datasetAuthor;
    private $datasetContact;
    private $datasetFile;
    private $factory;

    protected function setUp(): void
    {
        $this->createTestSubmission();
        $this->factory = new SubmissionDatasetFactory($this->submission);
    }

    private function createTestSubmission(): void
    {
        $author = new AuthorAdapter('test', 'user', 'Dataverse', 'user@test.com');

        $file = new TemporaryFile();
        $file->setServerFileName('sample.pdf');
        $file->setOriginalFileName('sample.pdf');

        $datasetAuthor = new DatasetAuthor(
            $author->getFullName(),
            $author->getAffiliation(),
            $author->getOrcid()
        );

        $datasetContact = new DatasetContact(
            $author->getFullName(),
            $author->getEmail(),
            'Dataverse'
        );


        $datasetFile = new DatasetFile();
        $datasetFile->setOriginalFileName($file->getOriginalFileName());
        $datasetFile->setPath($file->getFilePath());

        $submission = new SubmissionAdapter();
        $submission->setRequiredData(
            909,
            'Example title',
            'Example abstract',
            'Other',
            array('test'),
            'test citation',
            $datasetContact,
            'user, test (via Dataverse)',
            array($author),
            array($file)
        );

        $this->datasetAuthor = $datasetAuthor;
        $this->datasetContact = $datasetContact;
        $this->datasetFile = $datasetFile;
        $this->submission = $submission;
    }

    public function testCreateDatasetWithSubmissionData(): void
    {
        $dataset = $this->factory->getDataset();

        $this->assertEquals($dataset->getTitle(), $this->submission->getTitle());
        $this->assertEquals($dataset->getDescription(), $this->submission->getAbstract());
        $this->assertEquals($dataset->getSubject(), $this->submission->getSubject());
        $this->assertEquals($dataset->getAuthors(), array($this->datasetAuthor));
        $this->assertEquals($dataset->getContact(), $this->datasetContact);
        $this->assertEquals($dataset->getDepositor(), $this->submission->getDepositor());
        $this->assertEquals($dataset->getKeywords(), $this->submission->getKeywords());
        $this->assertEquals($dataset->getPubCitation(), $this->submission->getCitation());
        $this->assertEquals($dataset->getFiles(), array($this->datasetFile));
    }
}
