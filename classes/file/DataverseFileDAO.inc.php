<?php

import('lib.pkp.classes.db.DAO');

use Illuminate\Database\Capsule\Manager as Capsule;

class DataverseFileDAO extends DAO
{
    function getById(int $dataverseFileId): DataverseFile
    {
        $result = Capsule::table('dataverse_files')
            ->where('file_id', $dataverseFileId)
            ->get();

        $dataverseFile = null;
        foreach ($result->toArray() as $row) {
            $dataverseFile = $this->_fromRow(get_object_vars($row));
        }

		return $dataverseFile;
	}

    function getBySubmissionFileId(int $submissionFileId): DataverseFile
    {
        $result = Capsule::table('dataverse_files')
            ->where('submission_file_id', $dataverseFileId)
            ->get();

        $dataverseFile = null;
        foreach ($result->toArray() as $row) {
            $dataverseFile = $this->_fromRow(get_object_vars($row));
        }

		return $dataverseFile;
	}

    public function insertObject(DataverseFile $dataverseFile): int
    {
        Capsule::table('dataverse_files')
            ->insert(array(
                'study_id'              =>  (int)$dataverseFile->getStudyId(),
                'submission_id'         =>  (int)$dataverseFile->getSubmissionId(),
                'submission_file_id'    =>  (int)$dataverseFile->getSubmissionFileId(),
                'content_uri'           =>  $dataverseFile->getContentUri(),
            ));

        $dataverseFile->setFileId($this->getInsertDataverseFileId());
		return $dataverseFile->getFileId();
    }

    function getInsertDataverseFileId(): int
    {
		return $this->_getInsertId('dataverse_file', 'file_id');
	}

    function updateObject(DataverseFile $dataverseFile): void
    {
		Capsule::table('dataverse_files')
		->where('file_id', (int) $dataverseFile->getFileId())
		->update([
			'study_id'              =>  (int)$dataverseFile->getStudyId(),
            'submission_id'         =>  (int)$dataverseFile->getSubmissionId(),
            'submission_file_id'    =>  (int)$dataverseFile->getSubmissionFileId(),
            'content_uri'           =>  $dataverseFile->getContentUri()
		]);
	}

    function _fromRow($row): DataverseFile
    {
        $dataverseFile = new DataverseFile();
        
		$dataverseFile->setFileId($row['file_id']);
        $dataverseFile->setStudyId($row['study_id']);
        $dataverseFile->setSubmissionId($row['submission_id']);
        $dataverseFile->setSubmissionFileId($row['submission_file_id']);
        $dataverseFile->setContentUri($row['content_uri']);

		return $dataverseFile;
	}
}

?>