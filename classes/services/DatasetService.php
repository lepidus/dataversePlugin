<?php

namespace APP\plugins\generic\dataverse\classes\services;

use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use APP\plugins\generic\dataverse\classes\deposit\DepositRepository;
use APP\plugins\generic\dataverse\classes\deposit\DepositWorkflow;
use APP\core\Application;
use PKP\core\Core;
use APP\core\Request;
use PKP\db\DAORegistry;
use PKP\security\Role;
use PKP\mail\Mailable;
use Illuminate\Support\Facades\Mail;
use APP\notification\Notification;
use APP\notification\NotificationManager;
use APP\log\event\SubmissionEventLogEntry;
use PKP\log\SubmissionEmailLogEntry;
use APP\plugins\generic\dataverse\classes\services\DataverseService;
use APP\plugins\generic\dataverse\classes\services\DataStatementService;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use APP\plugins\generic\dataverse\classes\entities\Dataset;
use APP\plugins\generic\dataverse\classes\dataverseStudy\DataverseStudy;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\classes\facades\Repo;

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
        $dataverseClient = new DataverseClient();
        $files = array_values($dataset->getFiles());
        try {
            $completed = DB::table('dataverse_deposits')->where('submission_id', $submission->getId())
                ->where('state', 'complete')->first();
            if ($completed) {
                $study = Repo::dataverseStudy()->getBySubmissionId($submission->getId());
                if (!$study || $study->getPersistentId() !== $completed->persistent_id) {
                    throw new \RuntimeException('The completed deposit association changed.');
                }
                if (empty($files)) {
                    $this->clearDepositedDrafts($submission->getId(), json_decode($completed->manifest, true, 512, JSON_THROW_ON_ERROR));
                    return ['status' => 'Success'];
                }
            }
            $configuration = DAORegistry::getDAO('DataverseConfigurationDAO')->get($submission->getData('contextId'));
            $manifest = [];
            foreach ($files as $file) {
                $path = $file->getPath();
                $checksum = hash_file('sha256', $path);
                $size = filesize($path);
                if ($checksum === false || $size === false) {
                    throw new \RuntimeException('Cannot read the deposit source file.');
                }
                $manifest[] = [
                    'sourceId' => $file->getId(),
                    'repository' => $configuration->getDataverseUrl(),
                    'name' => $file->getOriginalFileName(),
                    'size' => $size,
                    'sha256' => $checksum,
                ];
            }
            if (!$manifest) {
                throw new \RuntimeException('The deposit has no source files.');
            }
            $workflow = new DepositWorkflow(new DepositRepository());
            $workflow->run(
                $submission->getId(),
                $manifest,
                function () use ($dataverseClient, $dataset): string {
                    return $dataverseClient->getDatasetActions()->create($dataset)->getPersistentId();
                },
                function (string $persistentId, int $key) use ($dataverseClient, $files): array {
                    $file = $files[$key];
                    return $dataverseClient->getDatasetFileActions()->add(
                        $persistentId,
                        $file->getOriginalFileName(),
                        $file->getPath()
                    );
                },
                function (string $persistentId) use ($submission): void {
                    $this->createStudy($submission, $persistentId);
                    $this->completeDeposit($submission, $persistentId);
                }
            );
        } catch (\Throwable $e) {
            $operation = null;
            try {
                $operation = DB::table('dataverse_deposits')->where('submission_id', $submission->getId())->first();
                $this->registerAndNotifyError($submission, 'plugins.generic.dataverse.error.depositPending', ['error' => get_class($e)]);
            } catch (\Throwable $reportingError) {
                error_log('Dataverse deposit reporting failed: submission=' . $submission->getId()
                    . ' exception=' . get_class($reportingError));
            }
            error_log('Dataverse deposit requires attention: submission=' . $submission->getId()
                . ' state=' . ($operation->state ?? 'unreserved')
                . ' doi=' . ($operation->persistent_id ?? 'unknown')
                . ' category=' . ($e instanceof DataverseException ? $e->getFailureCategory() : 'local')
                . ' exception=' . get_class($e));
            return [
                'status' => 'Error',
                'message' => 'plugins.generic.dataverse.error.depositPending',
                'messageParams' => ['error' => __('plugins.generic.dataverse.error.depositPending')],
            ];
        }
        $this->clearDepositedDrafts($submission->getId(), $manifest);
        return ['status' => 'Success'];
    }

    private function clearDepositedDrafts(int $submissionId, array $manifest): void
    {
        $sourceIds = array_column($manifest, 'sourceId');
        foreach (Repo::draftDatasetFile()->getBySubmissionId($submissionId) as $draftFile) {
            if (in_array($draftFile->getFileId(), $sourceIds, true)) {
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

        foreach ($data as $name => $value) {
            if ($name == 'relationType') {
                $dataset->getRelatedPublication()->setData('RelationType', $value);
                continue;
            }

            $dataset->setData($name, $value);
        }

        $study = Repo::dataverseStudy()->getByPersistentId($dataset->getPersistentId());
        $submission = Repo::submission()->get($study->getSubmissionId());

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

        (new DepositRepository())->retire($submission->getId(), function () use ($study, $submission): void {
            $currentStudy = Repo::dataverseStudy()->getBySubmissionId($study->getSubmissionId());
            if (!$currentStudy || $currentStudy->getId() !== $study->getId()) {
                throw new \RuntimeException('The dataset association changed during this request.');
            }
            Repo::dataverseStudy()->delete($study);

            $publication = $submission->getCurrentPublication();
            $dataStatementTypes = $publication->getData('dataStatementTypes');

            if (($key = array_search(DataStatementService::DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED, $dataStatementTypes)) !== false) {
                unset($dataStatementTypes[$key]);
                sort($dataStatementTypes);
            }

            Repo::publication()->edit($publication, ['dataStatementTypes' => $dataStatementTypes]);
        });

        $request = Application::get()->getRequest();
        $router = $request->getRouter();
        $handler = $router->getHandler();
        $userRoles = (array) $handler->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);

        if (in_array(Role::ROLE_ID_MANAGER, $userRoles) && $deleteMessage) {
            $this->sendEmailToDatasetAuthor($request, $dataset, $submission, $deleteMessage);
        }

        $this->registerEventLog(
            $submission,
            'plugins.generic.dataverse.log.researchDataDeleted'
        );
    }

    public function disassociate(DataverseStudy $study): void
    {
        $submission = Repo::submission()->get($study->getSubmissionId());
        $publication = $submission->getCurrentPublication();
        $dataStatementTypes = $publication->getData('dataStatementTypes');

        (new DepositRepository())->retire($submission->getId(), function () use ($study, $publication, $dataStatementTypes): void {
            $currentStudy = Repo::dataverseStudy()->getBySubmissionId($study->getSubmissionId());
            if (!$currentStudy || $currentStudy->getId() !== $study->getId()) {
                throw new \RuntimeException('The dataset association changed during this request.');
            }
            Repo::dataverseStudy()->delete($study);

            if (($key = array_search(DataStatementService::DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED, $dataStatementTypes)) !== false) {
                unset($dataStatementTypes[$key]);
                sort($dataStatementTypes);
            }
            Repo::publication()->edit($publication, ['dataStatementTypes' => $dataStatementTypes]);
        });

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
            if (Repo::dataverseStudy()->getBySubmissionId($submissionId)
                || DB::table('dataverse_deposits')->where('submission_id', $submissionId)->where('state', '!=', 'retired')->exists()) {
                return ['status' => DataverseService::STATUS_ERROR, 'message' => 'plugins.generic.dataverse.error.depositPending'];
            }
            $this->createStudy($submission, $persistentId);
            return ['status' => DataverseService::STATUS_SUCCESS];
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
                ['dataverseError' => $e->getMessage()]
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
        $emailTemplate = Repo::emailTemplate()->getByKey(
            $context->getId(),
            'DATASET_DELETE_NOTIFICATION'
        );

        $email = new Mailable();
        $email->from($context->getData('contactEmail'), $context->getData('contactName'));
        $email->to([['name' => $datasetContact->getName(), 'email' => $datasetContact->getEmail()]]);
        $email->subject($emailTemplate->getLocalizedData('subject'));
        $email->body($deleteMessage);

        try {
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

    private function logEmail($request, $email, $submission): void
    {
        $user = ($request) ? $request->getUser() : null;
        $submissionEmailLogDao = DAORegistry::getDAO('SubmissionEmailLogDAO');
        $submissionEmailLogDao->logMailable(
            SubmissionEmailLogEntry::SUBMISSION_EMAIL_EDITOR_NOTIFY_AUTHOR,
            $email,
            $submission,
            $user
        );
    }
}
