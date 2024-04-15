<?php

namespace APP\plugins\generic\dataverse\classes\dataverseStudy;

use Illuminate\Support\Facades\DB;
use APP\plugins\generic\dataverse\classes\dataverseStudy\DataverseStudy;

class DAO extends \PKP\db\DAO
{
    private $table = 'dataverse_studies';

    public function newDataObject(): DataverseStudy
    {
        return app(DataverseStudy::class);
    }

    public function get(int $studyId): ?DataverseStudy
    {
        $result = DB::table($this->table)
            ->where('study_id', $studyId)
            ->first();

        return is_null($result) ? null : $this->fromRow(get_object_vars($result));
    }

    public function getBySubmissionId(int $submissionId): ?DataverseStudy
    {
        $result = DB::table($this->table)
            ->where('submission_id', $submissionId)
            ->first();

        return is_null($result) ? null : $this->fromRow(get_object_vars($result));
    }

    public function getByPersistentId(string $persistentId): ?DataverseStudy
    {
        $result = DB::table($this->table)
            ->where('persistent_id', $persistentId)
            ->first();

        return is_null($result) ? null : $this->fromRow(get_object_vars($result));
    }

    public function insert(DataverseStudy $study): int
    {
        DB::table($this->table)
            ->insert([
                'submission_id'     =>  (int) $study->getSubmissionId(),
                'edit_uri'          =>  $study->getEditUri(),
                'edit_media_uri'    =>  $study->getEditMediaUri(),
                'statement_uri'     =>  $study->getStatementUri(),
                'persistent_uri'    =>  $study->getPersistentUri(),
                'persistent_id'     =>  $study->getPersistentId()
            ]);

        return $this->_getInsertId($this->table, 'study_id');
    }

    public function updateStudy(DataverseStudy $study): void
    {
        DB::table($this->table)
            ->where('study_id', $study->getId())
            ->update(array(
                'edit_uri'          =>  $study->getEditUri(),
                'edit_media_uri'    =>  $study->getEditMediaUri(),
                'statement_uri'     =>  $study->getStatementUri(),
                'persistent_uri'    =>  $study->getPersistentUri(),
                'persistent_id'     =>  $study->getPersistentId()
            ));
    }

    public function delete(DataverseStudy $study): void
    {
        DB::table($this->table)
            ->where('study_id', $study->getId())
            ->delete();
    }

    public function fromRow(array $row): DataverseStudy
    {
        $study = new DataverseStudy();
        $study->setId($row['study_id']);
        $study->setSubmissionId($row['submission_id']);
        $study->setEditUri($row['edit_uri']);
        $study->setEditMediaUri($row['edit_media_uri']);
        $study->setStatementUri($row['statement_uri']);
        $study->setPersistentUri($row['persistent_uri']);
        $study->setPersistentId($row['persistent_id']);

        return $study;
    }
}
