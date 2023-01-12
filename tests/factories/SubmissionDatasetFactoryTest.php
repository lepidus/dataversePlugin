<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.adapters.AuthorAdapter');
import('plugins.generic.dataverse.classes.adapters.SubmissionAdapter');
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
            null,
            array($author),
            array($file)
        );

        $datasetAuthor = array(
            'authorName' => $author->getFullName(),
            'affiliation' => $author->getAffiliation(),
            'identifier' => $author->getOrcid()
        );

        $datasetContact = array(
            'name' => $author->getFullName(),
            'email' => $author->getEmail()
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
        $this->assertEquals($dataset->getContacts(), array($this->datasetContact));
        $this->assertEquals($dataset->getKeywords(), $this->submission->getKeywords());
        $this->assertEquals($dataset->getCitation(), $this->submission->getCitation());
    }
}
