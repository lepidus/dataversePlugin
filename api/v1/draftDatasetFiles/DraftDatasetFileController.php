<?php

namespace APP\plugins\generic\dataverse\api\v1\draftDatasetFiles;

use APP\core\Application;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as IlluminateRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\core\Core;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\file\TemporaryFileManager;
use PKP\log\event\SubmissionFileEventLogEntry;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\security\Validation;

class DraftDatasetFileController extends PKPBaseController
{
    public const SCHEMA_NAME = 'draftDatasetFile';

    public function getHandlerPath(): string
    {
        return 'draftDatasetFiles';
    }

    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            'has.context',
            self::roleAuthorizer([
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
                Role::ROLE_ID_AUTHOR,
            ]),
        ];
    }

    public function getGroupRoutes(): void
    {
        Route::get('', $this->getMany(...))
            ->name('draftDatasetFile.getMany');

        Route::get('{fileId}', $this->get(...))
            ->name('draftDatasetFile.get')
            ->whereNumber('fileId');

        Route::get('{fileId}/download', $this->download(...))
            ->name('draftDatasetFile.download')
            ->whereNumber('fileId');

        Route::post('', $this->add(...))
            ->name('draftDatasetFile.add');

        Route::delete('', $this->delete(...))
            ->name('draftDatasetFile.delete');
    }

    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));

        return parent::authorize($request, $args, $roleAssignments);
    }

    public function getMany(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        $items = [];
        foreach (Repo::draftDatasetFile()->getBySubmissionId($submission->getId()) as $draftDatasetFile) {
            $items[] = $this->getFullProperties($draftDatasetFile);
        }

        ksort($items);

        return response()->json(['items' => $items], Response::HTTP_OK);
    }

    public function get(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $draftDatasetFile = $this->getSubmissionDraftDatasetFile($illuminateRequest->route('fileId'));

        if (!$draftDatasetFile) {
            return response()->json(['error' => __('api.404.resourceNotFound')], Response::HTTP_NOT_FOUND);
        }

        return response()->json($this->getFullProperties($draftDatasetFile), Response::HTTP_OK);
    }

    public function download(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $draftDatasetFile = $this->getSubmissionDraftDatasetFile($illuminateRequest->route('fileId'));

        if (!$draftDatasetFile) {
            return response()->json(['error' => __('api.404.resourceNotFound')], Response::HTTP_NOT_FOUND);
        }

        $temporaryFileManager = new TemporaryFileManager();
        $file = $temporaryFileManager->getFile($draftDatasetFile->getFileId(), $draftDatasetFile->getUserId());

        if (!$file) {
            return response()->json(['error' => __('api.404.resourceNotFound')], Response::HTTP_NOT_FOUND);
        }

        $filePath = $temporaryFileManager->getBasePath() . $file->getServerFileName();
        $temporaryFileManager->downloadByPath(
            $filePath,
            $file->getData('filetype'),
            false,
            $draftDatasetFile->getData('fileName')
        );

        exit;
    }

    public function add(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $temporaryFileId = $illuminateRequest->input('datasetFile')['temporaryFileId'] ?? null;

        if (!$temporaryFileId) {
            return response()->json(
                ['error' => __('api.400.paramNotSupported')],
                Response::HTTP_BAD_REQUEST
            );
        }

        $userId = $this->getRequest()->getUser()->getId();
        $file = (new TemporaryFileManager())->getFile((int) $temporaryFileId, $userId);

        if (!$file) {
            return response()->json(['error' => __('api.404.resourceNotFound')], Response::HTTP_NOT_FOUND);
        }

        $schemaService = app()->get('schema');
        $params = $schemaService->sanitize(self::SCHEMA_NAME, [
            'submissionId' => $submission->getId(),
            'userId' => $userId,
            'fileId' => $file->getId(),
            'fileName' => $file->getOriginalFileName(),
        ]);

        $draftDatasetFile = Repo::draftDatasetFile()->newDataObject();
        $draftDatasetFile->setAllData($params);
        $draftDatasetFile->setId(Repo::draftDatasetFile()->add($draftDatasetFile));

        $this->createFileEventLog($draftDatasetFile, 'plugins.generic.dataverse.log.researchDataFileAdded');

        return response()->json($this->getFullProperties($draftDatasetFile), Response::HTTP_OK);
    }

    public function delete(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $draftDatasetFile = $this->getSubmissionDraftDatasetFile($illuminateRequest->query('fileId'));

        if (!$draftDatasetFile) {
            return response()->json(
                ['error' => __('api.draftDatasetFile.404.drafDatasetFileNotFound')],
                Response::HTTP_NOT_FOUND
            );
        }

        $draftDatasetFileProps = $this->getFullProperties($draftDatasetFile);
        Repo::draftDatasetFile()->delete($draftDatasetFile);

        $this->createFileEventLog($draftDatasetFile, 'plugins.generic.dataverse.log.researchDataFileDeleted');

        return response()->json($draftDatasetFileProps, Response::HTTP_OK);
    }

    private function getSubmissionDraftDatasetFile($fileId)
    {
        if (!$fileId) {
            return null;
        }

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $draftDatasetFile = Repo::draftDatasetFile()->get((int) $fileId);

        if (!$draftDatasetFile || $draftDatasetFile->getSubmissionId() != $submission->getId()) {
            return null;
        }

        return $draftDatasetFile;
    }

    private function createFileEventLog($draftDatasetFile, string $messageKey): void
    {
        $user = $this->getRequest()->getUser();

        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $draftDatasetFile->getSubmissionId(),
            'userId' => Validation::loggedInAs() ?? $user->getId(),
            'eventType' => SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_UPLOAD,
            'message' => __($messageKey, ['filename' => $draftDatasetFile->getData('fileName')]),
            'isTranslated' => true,
            'dateLogged' => Core::getCurrentDate(),
        ]);
        Repo::eventLog()->add($eventLog);
    }

    private function getFullProperties($object): array
    {
        $schemaService = app()->get('schema');
        $props = $schemaService->getFullProps(self::SCHEMA_NAME);

        $objectProps = [];
        foreach ($props as $prop) {
            $objectProps[$prop] = $object->getData($prop);
        }

        $request = Application::get()->getRequest();
        $objectProps['downloadUrl'] = $request
            ->getDispatcher()
            ->url(
                $request,
                Application::ROUTE_API,
                $request->getContext()->getPath(),
                'draftDatasetFiles/' . $object->getId() . '/download',
                null,
                null,
                ['submissionId' => $object->getSubmissionId()]
            );

        return $objectProps;
    }
}
