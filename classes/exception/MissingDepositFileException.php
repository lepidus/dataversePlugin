<?php

namespace APP\plugins\generic\dataverse\classes\exception;

class MissingDepositFileException extends DataverseException
{
    public function __construct(private string $fileName)
    {
        parent::__construct(__('plugins.generic.dataverse.error.sourceFileMissing', ['fileName' => $fileName]));
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }
}
