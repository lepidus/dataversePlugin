<?php

use APP\plugins\generic\dataverse\classes\components\forms\SelectDataFilesForReviewForm;
use APP\plugins\generic\dataverse\classes\entities\DatasetFile;
use PKP\tests\PKPTestCase;

class SelectDataFilesForReviewFormTest extends PKPTestCase
{
    public function testDataFileNamesAreEscaped(): void
    {
        $datasetFile = new DatasetFile();
        $datasetFile->setId(1);
        $datasetFile->setFileName('<img src=x onerror=alert(1)>.csv');

        $form = new SelectDataFilesForReviewForm([$datasetFile]);

        $this->assertSame(
            '&lt;img src=x onerror=alert(1)&gt;.csv',
            $form->getField('selectedDataFilesForReview')->options[0]['label']
        );
    }
}
