<?php

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.db.DAO');
import('classes.submission.Submission');
import('classes.publication.Publication');
import('classes.article.Author');
import('plugins.generic.dataverse.classes.dataverseStudy.DataverseStudy');
import('plugins.generic.dataverse.classes.APACitation');

class APACitationTest extends PKPTestCase
{
    private $submission;
    private $publication;
    private $authors;

    public function setUp(): void
    {
        parent::setUp();

        $this->registerMockJournalDAO();
        $this->createTestSubmission();
        $this->createAuthors();
        $this->createTestPublication();
        $this->addCurrentPublicationToSubmission();
    }

    protected function getMockedDAOs(): array
    {
        return array('JournalDAO');
    }

    private function registerMockJournalDAO(): void
    {
        $journalDAO = $this->getMockBuilder(JournalDAO::class)
            ->setMethods(array('getById'))
            ->getMock();

        $journal = new Journal();
        $journal->setPrimaryLocale('pt_BR');
        $journal->setName('Preprints da Lepidus', 'pt_BR');

        $journalDAO->expects($this->any())
                   ->method('getById')
                   ->will($this->returnValue($journal));

        DAORegistry::registerDAO('JournalDAO', $journalDAO);
    }

    private function createAuthors(): void
    {
        $author = new Author();
        $author->setData('publicationId', 1234);
        $author->setGivenName('Iris', 'pt_BR');
        $author->setFamilyName('Castanheiras', 'pt_BR');
        $this->authors = [$author];
    }

    private function createTestSubmission(): void
    {
        $this->submission = new Submission();
        $this->submission->setId(1245);
        $this->submission->setData('contextId', 1);
        $this->submission->setData('dateSubmitted', '2021-05-31 15:38:24');
        $this->submission->setData('locale', 'pt_BR');
    }

    private function createTestPublication(): void
    {
        $this->publication = new Publication();
        $this->publication->setId(1234);
        $this->publication->setData('submissionId', 1245);
        $this->publication->setData('title', "The Rise of The Machine Empire", 'pt_BR');
        $this->publication->setData('authors', $this->authors);
        $this->publication->setData('locale', 'pt_BR');
        $this->publication->setData('relationStatus', '1');
        $this->publication->setData('pub-id::doi', '10.1234/LepidusPreprints.1245');
    }

    private function addCurrentPublicationToSubmission(): void
    {
        $this->submission->setData('currentPublicationId', 1234);
        $this->submission->setData('publications', array($this->publication));
    }

    public function testDatasetCitationGetsDoiMarkup(): void
    {
        $expectedDOI = 'https://doi.org/10.12345/FK2/NTF9X8';
        $dataCitation = "Iris Castanheiras, 2021, \"The Rise of The Machine Empire\", $expectedDOI, Demo Dataverse, V1, UNF:6:dEgtc5Z1MSF3u7c+kF4kXg== [fileUNF]";

        $study = new DataverseStudy();
        $study->setPersistentUri($expectedDOI);
        $study->setDataCitation($dataCitation);

        $apaCitation = new APACitation();
        $studyCitationMarkup = $apaCitation->getCitationAsMarkupByStudy($study);

        $expectedCitationMarkup = 'Iris Castanheiras, 2021, "The Rise of The Machine Empire", <a href="'. $expectedDOI .'">'. $expectedDOI .'</a>, Demo Dataverse, V1, UNF:6:dEgtc5Z1MSF3u7c+kF4kXg== [fileUNF]';
        $this->assertEquals($expectedCitationMarkup, $studyCitationMarkup);
    }

    public function testPreprintCitationIsAPA(): void
    {
        $expectedSubmissionCitation = 'Castanheiras, I. (2021). <em>The Rise of The Machine Empire</em>. Preprints da Lepidus.';
        $expectedSubmissionCitation .= ' <a href="https://doi.org/10.1234/LepidusPreprints.1245">https://doi.org/10.1234/LepidusPreprints.1245</a>';

        $apaCitation = new APACitation();
        $preprintCitation = $apaCitation->getFormattedCitationBySubmission($this->submission);

        $this->assertEquals($expectedSubmissionCitation, $preprintCitation);
    }

    public function testFamilyNameWithAccentOnTheFirstLetter(): void
    {
        $this->authors[0]->setGivenName('Átila', 'pt_BR');
        $this->authors[0]->setFamilyName('Álamo', 'pt_BR');

        $apaCitation = new APACitation();
        $preprintCitation = $apaCitation->getFormattedCitationBySubmission($this->submission);

        $expectedSubmissionCitation = 'Álamo, Á. (2021). <em>The Rise of The Machine Empire</em>. Preprints da Lepidus.';
        $expectedSubmissionCitation .= ' <a href="https://doi.org/10.1234/LepidusPreprints.1245">https://doi.org/10.1234/LepidusPreprints.1245</a>';
        $this->assertEquals($expectedSubmissionCitation, $preprintCitation);
    }

    public function testGivenNameWithAccentAnyLetterExceptFirstLetterAndFamilyNameWithoutAccent(): void
    {
        $this->authors[0]->setGivenName('Mário', 'pt_BR');
        $this->authors[0]->setFamilyName('Fernandes', 'pt_BR');

        $apaCitation = new APACitation();
        $preprintCitation = $apaCitation->getFormattedCitationBySubmission($this->submission);

        $expectedSubmissionCitation = 'Fernandes, M. (2021). <em>The Rise of The Machine Empire</em>. Preprints da Lepidus.';
        $expectedSubmissionCitation .= ' <a href="https://doi.org/10.1234/LepidusPreprints.1245">https://doi.org/10.1234/LepidusPreprints.1245</a>';
        $this->assertEquals($expectedSubmissionCitation, $preprintCitation);
    }

    public function testFamilyNameWithAccentOnTheFirstLetterAndGivenNameWithoutAccent(): void
    {
        $this->authors[0]->setGivenName('Lucas', 'pt_BR');
        $this->authors[0]->setFamilyName('Átila', 'pt_BR');

        $apaCitation = new APACitation();
        $preprintCitation = $apaCitation->getFormattedCitationBySubmission($this->submission);

        $expectedSubmissionCitation = 'Átila, L. (2021). <em>The Rise of The Machine Empire</em>. Preprints da Lepidus.';
        $expectedSubmissionCitation .= ' <a href="https://doi.org/10.1234/LepidusPreprints.1245">https://doi.org/10.1234/LepidusPreprints.1245</a>';
        $this->assertEquals($expectedSubmissionCitation, $preprintCitation);
    }

    public function testGivenNameAndFamilyNameWithAccentExceptFirstLetter(): void
    {
        $this->authors[0]->setGivenName('Cláudio', 'pt_BR');
        $this->authors[0]->setFamilyName('Sérgio', 'pt_BR');

        $apaCitation = new APACitation();
        $preprintCitation = $apaCitation->getFormattedCitationBySubmission($this->submission);

        $expectedSubmissionCitation = 'Sérgio, C. (2021). <em>The Rise of The Machine Empire</em>. Preprints da Lepidus.';
        $expectedSubmissionCitation .= ' <a href="https://doi.org/10.1234/LepidusPreprints.1245">https://doi.org/10.1234/LepidusPreprints.1245</a>';
        $this->assertEquals($expectedSubmissionCitation, $preprintCitation);
    }

    public function testGivenNameWithAccentOnTheFirstLetter(): void
    {
        $this->authors[0]->setGivenName('Ângelo', 'pt_BR');

        $apaCitation = new APACitation();
        $preprintCitation = $apaCitation->getFormattedCitationBySubmission($this->submission);

        $expectedSubmissionCitation = 'Castanheiras, Â. (2021). <em>The Rise of The Machine Empire</em>. Preprints da Lepidus.';
        $expectedSubmissionCitation .= ' <a href="https://doi.org/10.1234/LepidusPreprints.1245">https://doi.org/10.1234/LepidusPreprints.1245</a>';
        $this->assertEquals($expectedSubmissionCitation, $preprintCitation);
    }
}
