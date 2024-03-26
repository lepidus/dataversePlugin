<?php

namespace APP\plugins\generic\dataverse\classes\dataverseStudy;

use PKP\core\DataObject;

class DataverseStudy extends DataObject
{
    public function getId(): int
    {
        return $this->getData('studyId');
    }

    public function setId($studyId): void
    {
        $this->setData('studyId', $studyId);
    }

    public function getSubmissionId(): int
    {
        return $this->getData('submissionId');
    }

    public function setSubmissionId($submissionId): void
    {
        $this->setData('submissionId', $submissionId);
    }

    public function getEditUri(): string
    {
        return $this->getData('editUri');
    }

    public function setEditUri($editUri): void
    {
        $this->setData('editUri', $editUri);
    }

    public function getEditMediaUri(): string
    {
        return $this->getData('editMediaUri');
    }

    public function setEditMediaUri($editMediaUri): void
    {
        $this->setData('editMediaUri', $editMediaUri);
    }

    public function getStatementUri(): string
    {
        return $this->getData('statementUri');
    }

    public function setStatementUri($statementUri): void
    {
        $this->setData('statementUri', $statementUri);
    }

    public function getPersistentUri(): string
    {
        return $this->getData('persistentUri');
    }

    public function setPersistentUri($persistentUri): void
    {
        $this->setData('persistentUri', $persistentUri);
    }

    public function getDataCitation(): string
    {
        return $this->getData('dataCitation');
    }

    public function setDataCitation($dataCitation): void
    {
        $this->setData('dataCitation', $dataCitation);
    }

    public function getPersistentId(): string
    {
        return $this->getData('persistentId');
    }

    public function setPersistentId($persistentId): void
    {
        $this->setData('persistentId', $persistentId);
    }
}
