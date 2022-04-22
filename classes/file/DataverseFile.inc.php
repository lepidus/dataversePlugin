<?php

class DataverseFile extends DataObject
{
    public function setFileId(int $fileId): void
    {
        $this->setData('fileId', $fileId);
    }

    public function getFileId(): int
    {
        return $this->getData('fileId');
    }

    public function setStudyId(int $studyId): void
    {
        $this->setData('studyId', $studyId);
    }

    public function getStudyId(): int
    {
        return $this->getData('studyId');
    }

    public function setSubmissionId(int $submissionId): void
    {
        $this->setData('submissionId', $submissionId);
    }

    public function getSubmissionId(): int
    {
        return $this->getData('submissionId');
    }

    public function setSubmissionFileId(int $submissionFileId): void
    {
        $this->setData('submissionFileId', $submissionFileId);
    }

    public function getSubmissionFileId(): int
    {
        return $this->getData('submissionFileId');
    }

    public function setContentUri(string $contentUri): void
    {
        $this->setData('contentUri', $contentUri);
    }

    public function getContentUri(): string
    {
        return $this->getData('contentUri');
    }
}

?>