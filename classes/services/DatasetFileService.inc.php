<?php

import('plugins.generic.dataverse.dataverseAPI.DataverseClient');
import('lib.pkp.classes.log.SubmissionLog');
import('classes.log.SubmissionEventLogEntry');

class DatasetFileService
{
    public function add(DataverseStudy $study, int $fileId): void
    {
        $request = Application::get()->getRequest();
        $user = $request->getUser();

        import('lib.pkp.classes.file.TemporaryFileManager');
        $temporaryFileManager = new TemporaryFileManager();
        $file = $temporaryFileManager->getFile($fileId, $user->getId());

        try {
            $dataverseClient = new DataverseClient();
            $dataverseClient->getDatasetFileActions()->add(
                $study->getPersistentId(),
                $file->getOriginalFileName(),
                $file->getFilePath()
            );
        } catch (DataverseException $e) {
            error_log('Dataverse API error: ' . $e->getMessage());
            return;
        }

        $submission = Services::get('submission')->get($study->getSubmissionId());
        SubmissionLog::logEvent(
            $request,
            $submission,
            SUBMISSION_LOG_FILE_UPLOAD,
            'plugins.generic.dataverse.log.researchDataFileAdded',
            ['filename' => $file->getOriginalFileName()]
        );
    }

    public function delete(DataverseStudy $study, string $fileId, string $filename): void
    {
        try {
            $dataverseClient = new DataverseClient();
            $dataverseClient->getDatasetFileActions()->delete($fileId);
        } catch (DataverseException $e) {
            error_log('Dataverse API error: ' . $e->getMessage());
            return;
        }

        $request = Application::get()->getRequest();
        $submission = Services::get('submission')->get($study->getSubmissionId());
        SubmissionLog::logEvent(
            $request,
            $submission,
            SUBMISSION_LOG_FILE_UPLOAD,
            'plugins.generic.dataverse.log.researchDataFileDeleted',
            ['filename' => $filename]
        );
    }
}
