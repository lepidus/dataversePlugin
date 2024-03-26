<?php

namespace APP\plugins\generic\dataverse\classes\facades;

use APP\plugins\generic\dataverse\classes\dataverseStudy\Repository as DataverseStudyRepository;

class Repo extends \APP\facades\Repo
{
    public static function dataverseStudy(): DataverseStudyRepository
    {
        return app(DataverseStudyRepository::class);
    }
}
