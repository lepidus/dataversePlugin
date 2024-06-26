<?php

namespace APP\plugins\generic\dataverse\classes\services;

use APP\core\Application;
use PKP\file\TemporaryFileManager;
use PKP\log\event\SubmissionFileEventLogEntry;
use APP\plugins\generic\dataverse\classes\dataverseStudy\DataverseStudy;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\classes\services\DataverseService;
use APP\plugins\generic\dataverse\classes\facades\Repo;

class DatasetFileService extends DataverseService
{
    public function add(DataverseStudy $study, int $fileId): void
    {
        $request = Application::get()->getRequest();
        $user = $request->getUser();
        $submission = Repo::submission()->get($study->getSubmissionId());

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
            SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_UPLOAD
        );
    }

    public function delete(DataverseStudy $study, string $fileId, string $filename): void
    {
        $submission = Repo::submission()->get($study->getSubmissionId());

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
            SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_UPLOAD
        );
    }
}
