<?php

namespace APP\plugins\generic\dataverse\classes\draftDatasetFile;

use PKP\file\PKPFile;

class StoredDraftDatasetFile extends PKPFile
{
    public function __construct(private readonly string $path)
    {
        parent::__construct();
    }

    public function getFilePath(): string
    {
        return $this->path;
    }
}
