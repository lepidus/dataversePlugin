<?php

namespace APP\plugins\generic\dataverse\classes\draftDatasetFile;

use PKP\core\DataObject;

class DraftDatasetFile extends DataObject
{
    public function getSubmissionId(): int
    {
        return $this->getData('submissionId');
    }

    public function setSubmissionId(int $submissionId): void
    {
        $this->setData('submissionId', $submissionId);
    }

    public function getUserId(): int
    {
        return $this->getData('userId');
    }

    public function setUserId(int $userId): void
    {
        $this->setData('userId', $userId);
    }

    public function getFileId(): int
    {
        return $this->getData('fileId');
    }

    public function setFileId(int $fileId): void
    {
        $this->setData('fileId', $fileId);
    }

    public function getFileName(): string
    {
        return $this->getData('fileName');
    }

    public function setFileName(string $fileName): void
    {
        $this->setData('fileName', $fileName);
    }
}
