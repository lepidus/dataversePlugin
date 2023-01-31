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
    private $factory;

    protected function setUp(): void
    {
        $this->createTestSubmission();
        $this->factory = new SubmissionDatasetFactory($this->submission);
    }

    private function createTestSubmission(): void
    {
        $author = new AuthorAdapter('test', 'user', 'Dataverse', 'user@test.com');

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

        $file = new DraftDatasetFile();
        $file->setData('submissionId', 909);
        $file->setData('userId', 910);
        $file->setData('fileId', 911);
        $file->setData('fileName', 'sample.pdf');

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
        $this->assertEquals($dataset->getKeywords(), $this->submission->getKeywords());
        $this->assertEquals($dataset->getPubCitation(), $this->submission->getCitation());
    }
}
