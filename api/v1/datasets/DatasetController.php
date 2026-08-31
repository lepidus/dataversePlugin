<?php

namespace APP\plugins\generic\dataverse\api\v1\datasets;

use APP\core\Application;
use APP\log\event\SubmissionEventLogEntry;
use APP\plugins\generic\dataverse\classes\dataverseStudy\DataverseStudy;
use APP\plugins\generic\dataverse\classes\DraftDatasetFilesValidator;
use APP\plugins\generic\dataverse\classes\entities\Dataset;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\classes\factories\SubmissionDatasetFactory;
use APP\plugins\generic\dataverse\classes\services\DatasetFileService;
use APP\plugins\generic\dataverse\classes\services\DatasetService;
use APP\plugins\generic\dataverse\classes\services\DataverseService;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as IlluminateRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\core\Core;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\facades\Locale;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\security\Validation;

class DatasetController extends PKPBaseController
{
    public function getHandlerPath(): string
    {
        return 'datasets';
    }

    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            'has.context',
        ];
    }

    public function getGroupRoutes(): void
    {
        Route::middleware([
            self::roleAuthorizer([
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
                Role::ROLE_ID_AUTHOR,
            ]),
        ])->group(function () {
            Route::get('{studyId}', $this->get(...))
                ->name('dataset.get')
                ->whereNumber('studyId');

            Route::get('{studyId}/files', $this->getFiles(...))
                ->name('dataset.getFiles')
                ->whereNumber('studyId');

            Route::get('{studyId}/citation', $this->getCitation(...))
                ->name('dataset.getCitation')
                ->whereNumber('studyId');

            Route::get('{studyId}/inReview', $this->getInReview(...))
                ->name('dataset.getInReview')
                ->whereNumber('studyId');

            Route::post('', $this->add(...))
                ->name('dataset.add');

            Route::post('associate', $this->associate(...))
                ->name('dataset.associate');

            Route::post('{studyId}/file', $this->addFile(...))
                ->name('dataset.addFile')
                ->whereNumber('studyId');

            Route::put('{studyId}', $this->edit(...))
                ->name('dataset.edit')
                ->whereNumber('studyId');

            Route::put('{studyId}/publish', $this->publish(...))
                ->name('dataset.publish')
                ->whereNumber('studyId');

            Route::delete('{studyId}/file', $this->deleteFile(...))
                ->name('dataset.deleteFile')
                ->whereNumber('studyId');

            Route::delete('{studyId}', $this->delete(...))
                ->name('dataset.delete')
                ->whereNumber('studyId');
        });

        Route::middleware([
            self::roleAuthorizer([
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
            ]),
        ])->group(function () {
            Route::put('{studyId}/disassociate', $this->disassociate(...))
                ->name('dataset.disassociate')
                ->whereNumber('studyId');
        });

        Route::middleware([
            self::roleAuthorizer([
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
                Role::ROLE_ID_AUTHOR,
                Role::ROLE_ID_REVIEWER,
            ]),
        ])->group(function () {
            Route::get('{studyId}/file', $this->downloadFile(...))
                ->name('dataset.downloadFile')
                ->whereNumber('studyId');
        });
    }

    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));

        return parent::authorize($request, $args, $roleAssignments);
    }

    public function get(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $study = $this->getSubmissionStudy($illuminateRequest);

        if (!$study) {
            return $this->notFound();
        }

        try {
            $dataset = (new DataverseClient())->getDatasetActions()->get($study->getPersistentId());
        } catch (DataverseException $e) {
            if ($e->getCode() === 404) {
                Repo::dataverseStudy()->delete($study);

                return $this->notFound();
            }

            return $this->dataverseError($e);
        }

        return response()->json($dataset->getAllData(), Response::HTTP_OK);
    }

    public function edit(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $study = $this->getSubmissionStudy($illuminateRequest);

        if (!$study) {
            return $this->notFound();
        }

        $params = $illuminateRequest->input();
        $locale = Locale::getLocale();

        (new DatasetService())->update([
            'persistentId' => $study->getPersistentId(),
            'title' => $params['datasetTitle'],
            'description' => $params['datasetDescription'],
            'keywords' => $params['datasetKeywords'][$locale],
            'language' => $params['datasetLanguage'],
            'subject' => $params['datasetSubject'],
            'license' => $params['datasetLicense'],
            'relationType' => $params['datasetRelationType'],
        ]);

        return $this->get($illuminateRequest);
    }

    public function add(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $draftDatasetFiles = Repo::draftDatasetFile()->getBySubmissionId($submission->getId())->toArray();

        if (empty($draftDatasetFiles)) {
            return $this->error('plugins.generic.dataverse.researchDataFile.error', [], Response::HTTP_NOT_FOUND);
        }

        if (!(new DraftDatasetFilesValidator())->datasetHasReadmeFile($draftDatasetFiles)) {
            return $this->error('plugins.generic.dataverse.error.readmeFile.required', [], Response::HTTP_NOT_FOUND);
        }

        if (count($draftDatasetFiles) == 1) {
            return $this->error('plugins.generic.dataverse.error.notSolelyReadmeFile', [], Response::HTTP_NOT_FOUND);
        }

        $params = $illuminateRequest->input();
        $locale = Locale::getLocale();

        $dataset = (new SubmissionDatasetFactory($submission))->getDataset();
        $dataset->setTitle($params['datasetTitle']);
        $dataset->setDescription($params['datasetDescription']);
        $dataset->setKeywords((array) $params['datasetKeywords'][$locale]);
        $dataset->setLanguage($params['datasetLanguage']);
        $dataset->setSubject($params['datasetSubject']);
        $dataset->setLicense($params['datasetLicense']);

        $relatedPublication = $dataset->getRelatedPublication();
        $relatedPublication->setData('RelationType', $params['datasetRelationType']);
        $dataset->setRelatedPublication($relatedPublication);

        $excludedKeys = [
            'datasetTitle',
            'datasetDescription',
            'datasetKeywords',
            'datasetLanguage',
            'datasetSubject',
            'datasetLicense',
            'datasetRelationType',
        ];

        foreach ($params as $key => $value) {
            if (in_array($key, $excludedKeys)) {
                continue;
            }
            $metadataName = str_replace('dataset', '', $key);
            $metadataName = $metadataName === strtoupper($metadataName) ? $metadataName : lcfirst($metadataName);
            $dataset->setData($metadataName, $value);
        }

        if (!empty($dataset->getFiles())) {
            $depositInfo = (new DatasetService())->deposit($submission, $dataset);

            if ($depositInfo['status'] != 'Success') {
                return $this->error(
                    $depositInfo['message'] . '.author',
                    $depositInfo['messageParams'],
                    Response::HTTP_FORBIDDEN
                );
            }
        }

        return response()->json(['message' => 'ok'], Response::HTTP_OK);
    }

    public function associate(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $persistentId = $illuminateRequest->input('datasetPersistentId');

        $result = (new DatasetService())->associate($submission->getId(), $persistentId);

        if ($result['status'] !== DataverseService::STATUS_SUCCESS) {
            return $this->error($result['message'], [], $this->mapServiceStatusToHttpCode($result['status']));
        }

        return response()->json(['message' => 'ok'], Response::HTTP_OK);
    }

    public function disassociate(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $study = $this->getSubmissionStudy($illuminateRequest);

        if (!$study) {
            return $this->notFound();
        }

        (new DatasetService())->disassociate($study);

        return response()->json(['message' => 'ok'], Response::HTTP_OK);
    }

    public function publish(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $study = $this->getSubmissionStudy($illuminateRequest);

        if (!$study) {
            return $this->notFound();
        }

        $datasetActions = (new DataverseClient())->getDatasetActions();

        try {
            $dataset = $datasetActions->get($study->getPersistentId());

            if ($dataset->isPublished()) {
                return $this->error('api.dataset.403.alreadyPublished', [], Response::HTTP_FORBIDDEN);
            }

            $datasetActions->publish($study->getPersistentId());
            $dataset->setVersionState(Dataset::VERSION_STATE_RELEASED);
        } catch (DataverseException $e) {
            $message = 'plugins.generic.dataverse.error.publishFailed';

            error_log('Dataverse API error: ' . $e->getMessage());
            $this->createEventLog($study, $message, ['error' => $e->getMessage()]);

            return $this->error($message, ['error' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        }

        return response()->json($dataset->getAllData(), Response::HTTP_OK);
    }

    public function delete(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $study = $this->getSubmissionStudy($illuminateRequest);

        if (!$study) {
            return $this->notFound();
        }

        $deleteMessage = null;
        if ((int) $illuminateRequest->input('sendDeleteEmail') === 1) {
            $deleteMessage = $illuminateRequest->input('deleteMessage');
        }

        (new DatasetService())->delete($study, $deleteMessage);

        return response()->json(['message' => 'ok'], Response::HTTP_OK);
    }

    public function getFiles(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $study = $this->getSubmissionStudy($illuminateRequest);

        if (!$study) {
            return $this->notFound();
        }

        try {
            $datasetFiles = (new DataverseClient())
                ->getDatasetFileActions()
                ->getByDatasetId($study->getPersistentId());
        } catch (DataverseException $e) {
            return $this->dataverseError($e, 'Error getting dataset files: ');
        }

        $items = array_map(function ($datasetFile) use ($study) {
            $fileVars = $datasetFile->getVars();
            $fileVars['downloadUrl'] = $this->getFileActionUrl($study, [
                'fileId' => $datasetFile->getId(),
                'fileName' => $datasetFile->getFileName(),
            ]);
            return $fileVars;
        }, $datasetFiles);

        ksort($items);

        return response()->json(['items' => array_values($items)], Response::HTTP_OK);
    }

    public function addFile(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $study = $this->getSubmissionStudy($illuminateRequest);

        if (!$study) {
            return $this->notFound();
        }

        $temporaryFileId = $illuminateRequest->input('datasetFile')['temporaryFileId'] ?? null;

        if (!$temporaryFileId) {
            return $this->error('api.400.paramNotSupported', [], Response::HTTP_BAD_REQUEST);
        }

        (new DatasetFileService())->add($study, (int) $temporaryFileId);

        return response()->json(['message' => 'ok'], Response::HTTP_OK);
    }

    public function deleteFile(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $study = $this->getSubmissionStudy($illuminateRequest);

        if (!$study) {
            return $this->notFound();
        }

        (new DatasetFileService())->delete(
            $study,
            $illuminateRequest->query('fileId'),
            $illuminateRequest->query('fileName')
        );

        return response()->json(['message' => 'ok'], Response::HTTP_OK);
    }

    public function downloadFile(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $study = $this->getSubmissionStudy($illuminateRequest);

        if (!$study) {
            return $this->notFound();
        }

        (new DataverseClient())->getDatasetFileActions()->download(
            (int) $illuminateRequest->query('fileId'),
            $illuminateRequest->query('fileName')
        );

        exit;
    }

    public function getCitation(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $study = $this->getSubmissionStudy($illuminateRequest);

        if (!$study) {
            return $this->notFound();
        }

        $datasetIsPublished = (bool) $illuminateRequest->query('datasetIsPublished');

        try {
            $citationData = (new DataverseClient())
                ->getDatasetActions()
                ->getCitation($study->getPersistentId(), $datasetIsPublished);
        } catch (DataverseException $e) {
            error_log('Error getting citation: ' . $e->getMessage());

            return $this->error('api.error.researchDataCitationNotFound', [], $this->errorStatus($e));
        }

        return response()->json(['citation' => $citationData['citation']], Response::HTTP_OK);
    }

    public function getInReview(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $study = $this->getSubmissionStudy($illuminateRequest);

        if (!$study) {
            return $this->notFound();
        }

        try {
            $datasetLocks = (new DataverseClient())
                ->getDatasetActions()
                ->getDatasetLocks((int) $illuminateRequest->query('datasetId'));
        } catch (DataverseException $e) {
            return $this->dataverseError($e, 'Error getting dataset locks: ');
        }

        foreach ($datasetLocks as $lock) {
            if ($lock['lockType'] == 'InReview') {
                return response()->json(['inReview' => true], Response::HTTP_OK);
            }
        }

        return response()->json(['inReview' => false], Response::HTTP_OK);
    }

    private function getSubmissionStudy(IlluminateRequest $illuminateRequest): ?DataverseStudy
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $study = Repo::dataverseStudy()->get((int) $illuminateRequest->route('studyId'));

        if (!$study || $study->getSubmissionId() != $submission->getId()) {
            return null;
        }

        return $study;
    }

    private function getFileActionUrl(DataverseStudy $study, array $params): string
    {
        $request = Application::get()->getRequest();

        return $request->getDispatcher()->url(
            $request,
            Application::ROUTE_API,
            $request->getContext()->getPath(),
            'datasets/' . $study->getId() . '/file',
            null,
            null,
            array_merge(['submissionId' => $study->getSubmissionId()], $params)
        );
    }

    private function mapServiceStatusToHttpCode(string $status): int
    {
        return [
            DataverseService::STATUS_SUCCESS => Response::HTTP_OK,
            DataverseService::STATUS_NOT_FOUND => Response::HTTP_NOT_FOUND,
            DataverseService::STATUS_ERROR => Response::HTTP_FORBIDDEN,
        ][$status] ?? Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    private function notFound(): JsonResponse
    {
        return $this->error('api.404.resourceNotFound', [], Response::HTTP_NOT_FOUND);
    }

    private function dataverseError(DataverseException $e, string $logPrefix = 'Dataverse API error: '): JsonResponse
    {
        error_log($logPrefix . $e->getMessage());

        return $this->error($e->getUserMessageKey(), [], $this->errorStatus($e));
    }

    private function errorStatus(DataverseException $e): int
    {
        return array_key_exists($e->getCode(), Response::$statusTexts)
            ? $e->getCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    private function error(string $messageKey, array $params, int $status): JsonResponse
    {
        return response()->json(['error' => __($messageKey, $params)], $status);
    }

    private function createEventLog(DataverseStudy $study, string $messageKey, array $params): void
    {
        $user = $this->getRequest()->getUser();

        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $study->getSubmissionId(),
            'userId' => Validation::loggedInAs() ?? $user->getId(),
            'eventType' => SubmissionEventLogEntry::SUBMISSION_LOG_METADATA_UPDATE,
            'message' => __($messageKey, $params),
            'isTranslated' => true,
            'dateLogged' => Core::getCurrentDate(),
        ]);
        Repo::eventLog()->add($eventLog);
    }
}
