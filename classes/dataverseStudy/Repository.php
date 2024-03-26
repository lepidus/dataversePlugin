<?php

namespace APP\plugins\generic\dataverse\classes\dataverseStudy;

use APP\plugins\generic\dataverse\classes\dataverseStudy\DataverseStudy;
use APP\plugins\generic\dataverse\classes\dataverseStudy\DAO;

class Repository
{
    public $dao;

    public function __construct(DAO $dao)
    {
        $this->dao = $dao;
    }

    public function newDataObject(): DataverseStudy
    {
        return $this->dao->newDataObject();
    }

    public function get(int $studyId): ?DataverseStudy
    {
        return $this->dao->get($studyId);
    }

    public function getBySubmissionId(int $submissionId): ?DataverseStudy
    {
        return $this->dao->getBySubmissionId($submissionId);
    }

    public function getByPersistentId(string $persistentId): ?DataverseStudy
    {
        return $this->dao->getByPersistentId($persistentId);
    }

    public function add(DataverseStudy $study): int
    {
        return $this->dao->insert($study);
    }

    public function edit(DataverseStudy $study, array $params)
    {
        $newStudy = clone $study;
        $newStudy->setAllData(array_merge($newStudy->getAllData(), $params));

        $this->dao->updateStudy($newStudy);
    }

    public function delete(DataverseStudy $study)
    {
        $this->dao->delete($study);
    }
}
