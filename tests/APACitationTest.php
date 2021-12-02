<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.APACitation');

class APACitationTest extends PKPTestCase
{
    public function testHasDOIAsMarkup(): void
    {
        $expectedDOI = 'https://doi.org/10.12345/FK2/NTF9X8';
        $dataCitation = "Iris Castanheiras, 2021, \"The Rise of The Machine Empire\", $expectedDOI, Demo Dataverse, V1, UNF:6:dEgtc5Z1MSF3u7c+kF4kXg== [fileUNF]";

        $study = new DataverseStudy();
        $study->setPersistentUri($expectedDOI);
        $study->setDataCitation($dataCitation);

        $citation = new APACitation($study);
        
        $expectedCitationMarkup = 'Iris Castanheiras, 2021, "The Rise of The Machine Empire", <a href="'. $expectedDOI .'">'. $expectedDOI .'</a>, Demo Dataverse, V1, UNF:6:dEgtc5Z1MSF3u7c+kF4kXg== [fileUNF]';
        $this->assertEquals($expectedCitationMarkup, $citation->asMarkup());
    }
}