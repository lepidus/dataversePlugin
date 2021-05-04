<?php
import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.DataverseDAO');

class DataverseDAOTest extends PKPTestCase {

    public function testCredentialsAddedInDB(){
        $contextId = 1;
        $dvnUri = 'https://demo.dataverse.org';
        $apiToken = 'randomToken';

        $dataverseDAO = new DataverseDAO();
        $this->assertTrue($dataverseDAO->insertCredentialsOnDatabase($contextId, $dvnUri, $apiToken));
        $this->assertEquals([$apiToken, $dvnUri], $dataverseDAO->getCredentialsFromDatabase($contextId));
    }
}
?>