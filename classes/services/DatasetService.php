<?php

namespace APP\plugins\generic\dataverse\classes\services;

use APP\submission\Submission;
use APP\core\Application;
use PKP\core\Core;
use APP\core\Request;
use PKP\db\DAORegistry;
use PKP\security\Role;
use PKP\mail\Mailable;
use Illuminate\Support\Facades\Mail;
use PKP\notification\Notification;
use APP\notification\NotificationManager;
use APP\log\event\SubmissionEventLogEntry;
use PKP\log\SubmissionEmailLogEventType;
use APP\plugins\generic\dataverse\classes\services\DataverseService;
use APP\plugins\generic\dataverse\classes\services\DataStatementService;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use APP\plugins\generic\dataverse\classes\entities\Dataset;
use APP\plugins\generic\dataverse\classes\factories\SubmissionDatasetFactory;
use APP\plugins\generic\dataverse\classes\dataverseStudy\DataverseStudy;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\classes\exception\UploadNotSentException;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\classes\deposit\DepositRepository;
use APP\plugins\generic\dataverse\classes\deposit\DepositWorkflow;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class DatasetService extends DataverseService
{
    private function createStudy(Submission $submission, string $persistentId): void
    {
        $contextId = $submission->getData('contextId');
        $configuration = DAORegistry::getDAO('DataverseConfigurationDAO')->get($contextId);
        $swordAPIBaseUrl = $configuration->getDataverseServerUrl() . '/dvn/api/data-deposit/v1.1/swordv2/';

        $study = Repo::dataverseStudy()->newDataObject();
        $study->setSubmissionId($submission->getId());
        $study->setPersistentId($persistentId);
        $study->setEditUri($swordAPIBaseUrl . 'edit/study/' . $persistentId);
        $study->setEditMediaUri($swordAPIBaseUrl . 'edit-media/study/' . $persistentId);
        $study->setStatementUri($swordAPIBaseUrl . 'statement/study/' . $persistentId);
        $study->setPersistentUri('https://doi.org/' . str_replace('doi:', '', $persistentId));
        Repo::dataverseStudy()->add($study);
    }

    public function deposit(Submission $submission, Dataset $dataset): array
    {
        $submissionId = $submission->getId();
        $files = array_values($dataset->getFiles());

        try {
            $this->assertCompletedDepositIsAssociated($submissionId);

            $manifest = $this->createDepositManifest($submission, $files);
            $dataverseClient = new DataverseClient();
            (new DepositWorkflow(new DepositRepository()))->run(
                $submissionId,
                $manifest,
                fn (): string => $dataverseClient->getDatasetActions()->create($dataset)->getPersistentId(),
                fn (string $persistentId, int $key): array => $dataverseClient->getDatasetFileActions()->add(
                    $persistentId,
                    $files[$key]->getOriginalFileName(),
                    $files[$key]->getPath()
                ),
                function (string $persistentId) use ($submission): void {
                    $this->createStudy($submission, $persistentId);
                    $this->completeDeposit($submission, $persistentId);
                },
                function (string $persistentId) use ($dataverseClient, $dataset): void {
                    $dataset->setPersistentId($persistentId);
                    $dataverseClient->getDatasetActions()->update($dataset);
                },
                function (string $persistentId) use ($dataverseClient): void {
                    try {
                        $dataverseClient->getDatasetFileActions()->waitForIngest($persistentId);
                        $dataverseClient->getDatasetActions()->delete($persistentId);
                    } catch (DataverseException $e) {
                        if ($e->getCode() !== 404) {
                            throw $e;
                        }
                    }
                }
            );
        } catch (UploadNotSentException $e) {
            $params = ['error' => $e->getMessage(), 'fileName' => $e->getFileName()];
            $this->registerAndNotifyError($submission, 'plugins.generic.dataverse.error.datasetFileDeposit', $params);
            return [
                'status' => 'Error',
                'message' => 'plugins.generic.dataverse.error.datasetFileDeposit',
                'messageParams' => $params,
            ];
        } catch (Throwable $e) {
            return $this->reportFailedDeposit($submission, $e);
        }

        $this->clearDepositedDrafts($submissionId, $manifest);
        return ['status' => 'Success'];
    }

    private function assertCompletedDepositIsAssociated(int $submissionId): void
    {
        $completedOperation = DB::table('dataverse_deposits')
            ->where('submission_id', $submissionId)
            ->where('state', 'complete')
            ->first();

        if (!$completedOperation) {
            return;
        }

        $study = Repo::dataverseStudy()->getBySubmissionId($submissionId);
        if (!$study || $study->getPersistentId() !== $completedOperation->persistent_id) {
            throw new RuntimeException('The completed deposit association changed.');
        }
    }

    private function createDepositManifest(Submission $submission, array $files): array
    {
        $configuration = DAORegistry::getDAO('DataverseConfigurationDAO')->get($submission->getData('contextId'));
        $manifest = [];

        foreach ($files as $file) {
            $checksum = hash_file('sha256', $file->getPath());
            $size = filesize($file->getPath());
            if ($checksum === false || $size === false) {
                throw new RuntimeException('Cannot read the deposit source file.');
            }

            $manifest[] = [
                'sourceId' => $file->getId(),
                'repository' => $configuration->getDataverseUrl(),
                'name' => $file->getOriginalFileName(),
                'size' => $size,
                'sha256' => $checksum,
            ];
        }

        if (empty($manifest)) {
            throw new RuntimeException('The deposit has no source files.');
        }

        return $manifest;
    }

    private function reportFailedDeposit(Submission $submission, Throwable $e): array
    {
        $operation = null;
        $message = 'plugins.generic.dataverse.error.depositPending';
        $params = ['error' => __($message)];

        try {
            $operation = DB::table('dataverse_deposits')->where('submission_id', $submission->getId())->first();
            $outcomeIsUnknown = in_array($operation->state ?? null, ['creating', 'uploading'], true);
            if ($e instanceof DataverseException && !$outcomeIsUnknown) {
                $message = 'plugins.generic.dataverse.error.datasetDeposit';
                $params = ['error' => $e->getUserMessage()];
            }
            $this->registerAndNotifyError($submission, $message, $params);
        } catch (Throwable $reportingError) {
            error_log('Dataverse deposit reporting failed: submission=' . $submission->getId()
                . ' exception=' . get_class($reportingError) . ' message=' . $reportingError->getMessage());
        }

        error_log('Dataverse deposit failed: submission=' . $submission->getId()
            . ' state=' . ($operation->state ?? 'unreserved')
            . ' doi=' . ($operation->persistent_id ?? 'unknown')
            . ' category=' . ($e instanceof DataverseException ? $e->getFailureCategory() : 'local')
            . ' exception=' . get_class($e)
            . ' message=' . $e->getMessage());

        return ['status' => 'Error', 'message' => $message, 'messageParams' => $params];
    }

    private function clearDepositedDrafts(int $submissionId, array $manifest): void
    {
        $sourceIds = array_column($manifest, 'sourceId');

        foreach (Repo::draftDatasetFile()->getBySubmissionId($submissionId) as $draftFile) {
            if (in_array($draftFile->getData('fileId'), $sourceIds)) {
                Repo::draftDatasetFile()->delete($draftFile);
            }
        }
    }

    private function completeDeposit(Submission $submission, string $persistentId): void
    {
        $publication = $submission->getCurrentPublication();
        $dataStatementTypes = $publication->getData('dataStatementTypes');
        if (empty($dataStatementTypes)) {
            $dataStatementTypes = [DataStatementService::DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED];
        } elseif (!in_array(DataStatementService::DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED, $dataStatementTypes)) {
            $dataStatementTypes[] = DataStatementService::DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED;
        }

        Repo::publication()->edit($publication, ['dataStatementTypes' => $dataStatementTypes]);

        $this->registerEventLog(
            $submission,
            'plugins.generic.dataverse.log.researchDataDeposited',
            ['persistentId' => $persistentId],
            SubmissionEventLogEntry::SUBMISSION_LOG_SUBMISSION_SUBMIT
        );
    }

    public function update(array $data): void
    {
        try {
            $dataverseClient = new DataverseClient();
            $dataset = $dataverseClient->getDatasetActions()->get($data['persistentId']);
        } catch (DataverseException $e) {
            error_log('Dataverse error while getting dataset on dataset update: ' . $e->getMessage());
            return;
        }

        if ($dataset->isPublished()) {
            return;
        }

        $study = Repo::dataverseStudy()->getByPersistentId($dataset->getPersistentId());
        $submission = Repo::submission()->get($study->getSubmissionId());

        foreach ($data as $name => $value) {
            if ($name == 'relationType') {
                if (!$dataset->getRelatedPublication()) {
                    $dataset->setRelatedPublication(
                        (new SubmissionDatasetFactory($submission))
                            ->getDatasetRelatedPublication($submission->getCurrentPublication())
                    );
                }
                $dataset->getRelatedPublication()->setData('RelationType', $value);
                continue;
            }

            $dataset->setData($name, $value);
        }

        try {
            $dataverseClient->getDatasetActions()->update($dataset);
        } catch (DataverseException $e) {
            $this->registerAndNotifyError(
                $submission,
                'plugins.generic.dataverse.error.updateFailed',
                ['error' => $e->getMessage()]
            );
            return;
        }

        $this->registerEventLog(
            $submission,
            'plugins.generic.dataverse.log.researchDataUpdated'
        );
    }

    public function delete(DataverseStudy $study, ?string $deleteMessage): void
    {
        $submission = Repo::submission()->get($study->getSubmissionId());

        try {
            $dataverseClient = new DataverseClient();
            $dataset = $dataverseClient->getDatasetActions()->get($study->getPersistentId());
            $dataverseClient->getDatasetActions()->delete($dataset->getPersistentId());
        } catch (DataverseException $e) {
            $this->registerAndNotifyError(
                $submission,
                'plugins.generic.dataverse.error.deleteFailed',
                ['error' => $e->getMessage()]
            );
            return;
        }

        try {
            $this->removeStudy($study, $submission);
        } catch (RuntimeException $e) {
            error_log('Dataverse dataset ' . $dataset->getPersistentId()
                . ' was deleted, but its association was not removed: ' . $e->getMessage());
            return;
        }

        $request = Application::get()->getRequest();
        $user = $request->getUser();
        $isManager = $user && $user->hasRole([Role::ROLE_ID_MANAGER], $submission->getData('contextId'));

        if ($isManager && $deleteMessage) {
            $this->sendEmailToDatasetAuthor($request, $dataset, $submission, $deleteMessage);
        }

        $this->registerEventLog(
            $submission,
            'plugins.generic.dataverse.log.researchDataDeleted'
        );
    }

    public function removeMissingDataset(DataverseStudy $study): void
    {
        (new DepositRepository())->retire($study->getSubmissionId(), function () use ($study): void {
            Repo::dataverseStudy()->delete($study);
        });
    }

    public function disassociate(DataverseStudy $study): void
    {
        $submission = Repo::submission()->get($study->getSubmissionId());

        $this->removeStudy($study, $submission);

        $this->registerEventLog(
            $submission,
            'plugins.generic.dataverse.log.researchDataDisassociate'
        );
    }

    public function associate(int $submissionId, string $persistentId): array
    {
        $submission = Repo::submission()->get($submissionId);
        if (!$submission) {
            return ['status' => DataverseService::STATUS_NOT_FOUND, 'message' => 'api.404.resourceNotFound'];
        }

        $existingStudy = Repo::dataverseStudy()->getBySubmissionId($submissionId);
        if ($existingStudy) {
            return ['status' => DataverseService::STATUS_ERROR, 'message' => 'plugins.generic.dataverse.error.submissionHasStudy'];
        }

        if (!preg_match('/^doi:10\.\d{4,9}\/[-._;()\/:A-Z0-9]+$/i', $persistentId)) {
            return ['status' => DataverseService::STATUS_ERROR, 'message' => 'plugins.generic.dataverse.error.invalidPersistentId'];
        }

        $existingStudyWithPersistentId = Repo::dataverseStudy()->getByPersistentId($persistentId);
        if ($existingStudyWithPersistentId) {
            return ['status' => DataverseService::STATUS_ERROR, 'message' => 'plugins.generic.dataverse.error.associate.alreadyAssociated'];
        }

        try {
            $dataverseClient = new DataverseClient();
            $dataset = $dataverseClient->getDatasetActions()->get($persistentId);
        } catch (DataverseException $e) {
            return ['status' => DataverseService::STATUS_NOT_FOUND, 'message' => 'plugins.generic.dataverse.error.associate.notFound'];
        }

        return DB::transaction(function () use ($submission, $submissionId, $persistentId): array {
            (new DepositRepository())->lockSubmission($submissionId);
            if (Repo::dataverseStudy()->getBySubmissionId($submissionId)) {
                return [
                    'status' => DataverseService::STATUS_ERROR,
                    'message' => 'plugins.generic.dataverse.error.submissionHasStudy',
                ];
            }

            if ($this->hasActiveDeposit($submissionId)) {
                return ['status' => DataverseService::STATUS_ERROR, 'message' => 'plugins.generic.dataverse.error.depositPending'];
            }

            $this->createStudy($submission, $persistentId);
            return ['status' => DataverseService::STATUS_SUCCESS];
        });
    }

    private function hasActiveDeposit(int $submissionId): bool
    {
        return DB::table('dataverse_deposits')
            ->where('submission_id', $submissionId)
            ->where('state', '!=', 'retired')
            ->exists();
    }

    private function removeStudy(DataverseStudy $study, Submission $submission): void
    {
        (new DepositRepository())->retire($submission->getId(), function () use ($study, $submission): void {
            $currentStudy = Repo::dataverseStudy()->getBySubmissionId($study->getSubmissionId());
            if (!$currentStudy || $currentStudy->getId() !== $study->getId()) {
                throw new RuntimeException('The dataset association changed during this request.');
            }

            Repo::dataverseStudy()->delete($study);

            $publication = Repo::publication()->get($submission->getCurrentPublication()->getId());
            $dataStatementTypes = $publication->getData('dataStatementTypes') ?? [];
            if (($key = array_search(DataStatementService::DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED, $dataStatementTypes)) !== false) {
                unset($dataStatementTypes[$key]);
                sort($dataStatementTypes);
            }

            Repo::publication()->edit($publication, ['dataStatementTypes' => $dataStatementTypes]);
        });
    }

    public function publish(Submission $submission, DataverseStudy $study): void
    {
        try {
            $dataverseClient = new DataverseClient();

            $dataset = $dataverseClient->getDatasetActions()->get($study->getPersistentId());

            if ($dataset->isPublished()) {
                return;
            }

            $dataverseClient->getDatasetActions()->publish($study->getPersistentId());
        } catch (DataverseException $e) {
            $this->registerAndNotifyError(
                $submission,
                'plugins.generic.dataverse.error.publishFailed',
                ['error' => $e->getMessage()]
            );
            return;
        }

        $this->registerEventLog(
            $submission,
            'plugins.generic.dataverse.log.researchDataPublished',
            [],
            SubmissionEventLogEntry::SUBMISSION_LOG_METADATA_PUBLISH
        );
    }

    private function sendEmailToDatasetAuthor(
        Request $request,
        Dataset $dataset,
        Submission $submission,
        ?string $deleteMessage
    ): void {
        $context = $request->getContext();
        $datasetContact = $dataset->getContact();

        $email = new Mailable();
        $email->from($context->getData('contactEmail'), $context->getData('contactName'));
        $email->to([['name' => $datasetContact->getName(), 'email' => $datasetContact->getEmail()]]);
        $email->body($deleteMessage);

        try {
            $emailTemplate = Repo::emailTemplate()->getByKey(
                $context->getId(),
                'DATASET_DELETE_NOTIFICATION'
            );

            if (is_null($emailTemplate)) {
                throw new \Exception('Email template DATASET_DELETE_NOTIFICATION not found');
            }

            $email->subject($emailTemplate->getLocalizedData('subject'));

            Mail::send($email);
            $this->logEmail($request, $email, $submission);
        } catch (\Exception $e) {
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification(
                $request->getUser()->getId(),
                Notification::NOTIFICATION_TYPE_ERROR,
                ['contents' => __('email.compose.error')]
            );
        }
    }

    private function logEmail(?Request $request, Mailable $email, Submission $submission): void
    {
        $user = $request ? $request->getUser() : null;
        Repo::emailLogEntry()->logMailable(
            SubmissionEmailLogEventType::EDITOR_NOTIFY_AUTHOR,
            $email,
            $submission,
            $user
        );
    }
}
