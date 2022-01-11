<?php

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.db.DAO');
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
    
    protected function getMockedDAOs() {
		return array('JournalDAO');
	}
    
    private function registerMockJournalDAO() {
		$journalDAO = $this->getMockBuilder(JournalDAO::class)
			->setMethods(array('getById'))
			->getMock();

		$journal = new Journal();
        $journal->setPrimaryLocale('en_US');
        $journal->setName('Preprints da Lepidus', 'en_US');

		$journalDAO->expects($this->any())
		           ->method('getById')
		           ->will($this->returnValue($journal));

		DAORegistry::registerDAO('JournalDAO', $journalDAO);
	}

    private function createAuthors(): void
    {
        $author = new Author();
        $author->setData('publicationId', 1234);
        $author->setGivenName('Iris', 'en_US');
        $author->setFamilyName('Castanheiras', 'en_US');
        $this->authors = [$author];
    }

    private function createTestSubmission(): void
    {
        $this->submission = new Submission();
        $this->submission->setId(1245);
        $this->submission->setData('contextId', 1);
        $this->submission->setData('dateSubmitted', '2021-05-31 15:38:24');
        $this->submission->setData('locale', 'en_US');
    }
    
    private function createTestPublication(): void
    {
        $this->publication = new Publication();
        $this->publication->setId(1234);
        $this->publication->setData('submissionId', 1245);
        $this->publication->setData('title', "The Rise of The Machine Empire", 'en_US');
        $this->publication->setData('authors', $this->authors);
        $this->publication->setData('locale', 'en_US');
        $this->publication->setData('relationStatus', '1');
    }

    private function addCurrentPublicationToSubmission(): void
    {
        $this->submission->setData('currentPublicationId', 1234);
        $this->submission->setData('publications', array($this->publication));
    }
    
    public function testHasDOIAsMarkup(): void
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
        $expectedSubmissionCitation = 'Castanheiras, I. (2021). <em>The Rise of The Machine Empire</em>. Preprints da Lepidus';
        
        $apaCitation = new APACitation();
        $preprintCitation = $apaCitation->getFormattedCitationBySubmission($this->submission);

        $this->assertEquals($expectedSubmissionCitation, $preprintCitation);
    }
}
