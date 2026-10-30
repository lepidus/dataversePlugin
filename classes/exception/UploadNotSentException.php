<?php

namespace APP\plugins\generic\dataverse\classes\exception;

class UploadNotSentException extends DataverseException
{
    public function __construct(private string $fileName, DataverseException $reason)
    {
        parent::__construct($reason->getMessage(), $reason->getCode(), $reason);
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function wasRejected(): bool
    {
        return true;
    }

    public function getFailureCategory(): string
    {
        return $this->getPrevious()->getFailureCategory();
    }
}
