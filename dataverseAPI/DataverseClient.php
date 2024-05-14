<?php

namespace APP\plugins\generic\dataverse\dataverseAPI;

use APP\plugins\generic\dataverse\dataverseAPI\actions\DataverseCollectionActions;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DatasetActions;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DatasetFileActions;

class DataverseClient
{
    public function getDataverseCollectionActions(): DataverseCollectionActions
    {
        return new DataverseCollectionActions();
    }

    public function getDatasetActions(): DatasetActions
    {
        return new DatasetActions();
    }

    public function getDatasetFileActions(): DatasetFileActions
    {
        return new DatasetFileActions();
    }
}
