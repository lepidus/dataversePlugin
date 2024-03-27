<?php

namespace APP\plugins\generic\dataverse\classes\facades;

use APP\plugins\generic\dataverse\classes\dataverseStudy\Repository as DataverseStudyRepository;
use APP\plugins\generic\dataverse\classes\draftDatasetFile\Repository as DraftDatasetFileRepository;

class Repo extends \APP\facades\Repo
{
    public static function dataverseStudy(): DataverseStudyRepository
    {
        return app(DataverseStudyRepository::class);
    }

    public static function draftDatasetFile(): DraftDatasetFileRepository
    {
        return app(DraftDatasetFileRepository::class);
    }
}
