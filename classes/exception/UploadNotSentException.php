<?php

namespace APP\plugins\generic\dataverse\classes\exception;

/** The upload failed before a request was sent to Dataverse. */
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

    public function getFailureCategory(): string
    {
        return $this->getPrevious()->getFailureCategory();
    }
}
