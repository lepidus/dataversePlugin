<?php

import('plugins.generic.dataverse.dataverseAPI.DataverseClient');
import('plugins.generic.dataverse.classes.services.DataverseService');

class DatasetFileService extends DataverseService
{
    public function add(DataverseStudy $study, int $fileId): void
    {
        $request = Application::get()->getRequest();
        $user = $request->getUser();
        $submission = Services::get('submission')->get($study->getSubmissionId());

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
            $this->registerAndNotifyError(
                $submission,
                'plugins.generic.dataverse.error.addFileFailed',
                [
                    'filename' => $file->getOriginalFileName(),
                    'error' => $e->getMessage()
                ]
            );
            return;
        }

        $this->registerEventLog(
            $submission,
            'plugins.generic.dataverse.log.researchDataFileAdded',
            ['filename' => $file->getOriginalFileName()],
            SUBMISSION_LOG_FILE_UPLOAD
        );
    }

    public function delete(DataverseStudy $study, string $fileId, string $filename): void
    {
        $submission = Services::get('submission')->get($study->getSubmissionId());

        try {
            $dataverseClient = new DataverseClient();
            $dataverseClient->getDatasetFileActions()->delete($fileId);
        } catch (DataverseException $e) {
            $this->registerAndNotifyError(
                $submission,
                'plugins.generic.dataverse.error.deleteFileFailed',
                [
                    'filename' => $filename,
                    'error' => $e->getMessage()
                ]
            );
            return;
        }

        $this->registerEventLog(
            $submission,
            'plugins.generic.dataverse.log.researchDataFileDeleted',
            ['filename' => $filename],
            SUBMISSION_LOG_FILE_UPLOAD
        );
    }
}
