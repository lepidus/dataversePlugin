<?php

namespace APP\plugins\generic\dataverse\dataverseAPI\actions\interfaces;

use APP\plugins\generic\dataverse\classes\entities\DataverseCollection;

interface DataverseCollectionActionsInterface
{
    public function get(): DataverseCollection;

    public function getRoot(): DataverseCollection;

    public function publish(): void;
}
