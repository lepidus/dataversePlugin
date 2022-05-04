<?php

import('lib.pkp.tests.PKPTestCase');
import('classes.submission.Submission');
import('lib.pkp.classes.submission.SubmissionFile');
import('classes.publication.Publication');    
import('classes.article.Author');
import('classes.article.ArticleGalley');
import('plugins.generic.dataverse.classes.api.DataverseService');
import('plugins.generic.dataverse.classes.DataverseConfiguration');
import('plugins.generic.dataverse.DataversePlugin');

class DataverseServiceTest extends PKPTestCase {

    function createDataverseClientMock(): DataverseClient
    {
        $dataverseUrl = "https://demo.dataverse.org/dataverse/dataverseDeExemplo/";
        $apiToken = "apiRandom";

        $mockClient = $this->getMockBuilder(DataverseClient::class)
            ->setConstructorArgs([
                new DataverseConfiguration(
                    $dataverseUrl,
                    $apiToken),
                new DataversePlugin()
            ])
            ->setMethods(array('retrieveDepositReceipt'))
            ->getMock();

        $sacNewStatus = 200;
        $sacTheXml = '<sac_title>Dataverse de Exemplo Lepidus</sac_title>';
        $swordAppEntry = new SWORDAPPEntry($sacNewStatus, $sacTheXml);
        $swordAppEntry->sac_title = 'Dataverse de Exemplo Lepidus';

        $mockClient->expects($this->any())
            ->method('retrieveDepositReceipt')
            ->will(
                $this->returnValue($swordAppEntry));

        return $mockClient;
    }

    private function createTestSubmission(): Submission
    {
        $submission = new Submission();
        $submission->setId(1245);
        $submission->setData('contextId', 1);
        $submission->setData('dateSubmitted', '2021-05-31 15:38:24');
        $submission->setData('locale', 'en_US');


        $submissionFile = new SubmissionFile();
        $submissionFile->setId(1);
        $submissionFile->setData('genreId', 7);
        $submissionFile->setData('name', 'testSample.csv');
        $submissionFile->setData('path', 'path/to/file');
        $submissionFile->setData('publishData', true);
        $submissionFile->setData('sponsor', 'CAPES');

        $galley = new ArticleGalley();
        $galley->setLabel('CSV');
        $galley->setLocale('en_US');
        $galley->_submissionFile = $submissionFile;

        $author = new Author();
        $author->setData('publicationId', 1234);
        $author->setGivenName('Iris', 'en_US');
        $author->setFamilyName('Castanheiras', 'en_US');

        $publication = new Publication();
        $publication->setId(1234);
        $publication->setData('submissionId', $submission->getId());
        $publication->setData('locale', 'en_US');
        $publication->setData('title', "The Rise of The Machine Empire", 'en_US');
        $publication->setData('abstract', "This is an abstract / description");
        $publication->setData('authors', array($author));
        $publication->setData('keywords', array("en_US" => array("computer science", "testing")));
        $publication->setData('relationStatus', '1');
        $publication->setData('galleys', array($galley));

        $submission->setData('currentPublicationId', 1234);
        $submission->setData('publications', array($publication));

        return $submission;
    }

    function testIfSubmissionAdapterHasDatasetComponent(): void
    {
        $client = $this->createDataverseClientMock();
        $submission = $this->createTestSubmission();
        $service = new DataverseService($client);
        $service->setSubmission($submission);
        
        $this->assertTrue($service->hasDataSetComponent());
    }

    function testReturnDataverseNameLikeDataverseDeExemploLepidus(): void
    {
        $client = $this->createDataverseClientMock();

        $service = new DataverseService($client);
        $dataverseName = $service->getDataverseName();

        $this->assertEquals('Dataverse de Exemplo Lepidus', $dataverseName);
    }

}

?>