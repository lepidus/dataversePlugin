<?php

namespace APP\plugins\generic\dataverse\api\v1\dataverse;

use APP\core\Application;
use APP\plugins\generic\dataverse\classes\components\forms\AssociateDatasetForm;
use APP\plugins\generic\dataverse\classes\components\forms\DatasetMetadataForm;
use APP\plugins\generic\dataverse\classes\components\forms\DataStatementForm;
use APP\plugins\generic\dataverse\classes\components\forms\DeleteDatasetForm;
use APP\plugins\generic\dataverse\classes\components\listPanel\DatasetFilesListPanel;
use APP\plugins\generic\dataverse\classes\dataverseStudy\DataverseStudy;
use APP\plugins\generic\dataverse\classes\entities\Dataset;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\classes\factories\SubmissionDatasetFactory;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use APP\submission\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as IlluminateRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;

class DataverseController extends PKPBaseController
{
    public function getHandlerPath(): string
    {
        return 'dataverse';
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
        Route::get('researchData', $this->getResearchData(...))
            ->name('dataverse.researchData');

        Route::get('dataStatement', $this->getDataStatement(...))
            ->name('dataverse.dataStatement');
    }

    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));

        return parent::authorize($request, $args, $roleAssignments);
    }

    public function getResearchData(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $configuration = DAORegistry::getDAO('DataverseConfigurationDAO')
            ->get($submission->getData('contextId'));
        $study = Repo::dataverseStudy()->getBySubmissionId($submission->getId());

        $state = [
            'hasDataset' => !is_null($study),
            'additionalInstructions' => $configuration->getLocalizedAdditionalInstructions(),
            'canSendDeleteEmail' => $this->userHasRole(Role::ROLE_ID_MANAGER),
            'error' => null,
        ];

        try {
            $state += is_null($study)
                ? $this->getDepositState($submission)
                : $this->getDatasetState($submission, $study, $configuration->getDataverseServerUrl());
        } catch (DataverseException $e) {
            error_log('Dataverse API error while loading research data: ' . $e->getMessage());
            $state['error'] = __($e->getUserMessageKey());
        }

        return response()->json($state, Response::HTTP_OK);
    }

    public function getDataStatement(IlluminateRequest $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $publication = $submission->getLatestPublication();

        $saveFormUrl = $request->getDispatcher()->url(
            $request,
            Application::ROUTE_API,
            $request->getContext()->getPath(),
            'submissions/' . $submission->getId() . '/publications/' . $publication->getId()
        );

        $form = new DataStatementForm($saveFormUrl, $publication, 'workflow');

        return response()->json(['form' => $form->getConfig()], Response::HTTP_OK);
    }

    private function getDepositState(Submission $submission): array
    {
        $dataset = (new SubmissionDatasetFactory($submission))->getDataset();

        $files = [];
        foreach (Repo::draftDatasetFile()->getBySubmissionId($submission->getId()) as $draftDatasetFile) {
            $props = $draftDatasetFile->getAllData();
            $props['downloadUrl'] = $this->apiUrl(
                'draftDatasetFiles/' . $draftDatasetFile->getId() . '/download',
                ['submissionId' => $submission->getId()]
            );
            $files[] = $props;
        }
        ksort($files);

        $draftFilesUrl = $this->apiUrl('draftDatasetFiles', ['submissionId' => $submission->getId()]);

        return [
            'forms' => [
                'datasetMetadata' => (new DatasetMetadataForm(
                    $this->apiUrl('datasets', ['submissionId' => $submission->getId()]),
                    'POST',
                    $dataset,
                    'workflow'
                ))->getConfig(),
                'associateDataset' => (new AssociateDatasetForm(
                    $this->apiUrl('datasets/associate', ['submissionId' => $submission->getId()])
                ))->getConfig(),
            ],
            'filesListPanel' => $this->getFilesListPanelConfig(
                $draftFilesUrl,
                $draftFilesUrl,
                array_values($files)
            ),
        ];
    }

    private function getDatasetState(Submission $submission, DataverseStudy $study, string $serverUrl): array
    {
        $dataverseClient = new DataverseClient();
        $dataset = $dataverseClient->getDatasetActions()->get($study->getPersistentId());
        $datasetIsPublished = $dataset->isPublished();

        $datasetUrl = $this->apiUrl(
            'datasets/' . $study->getId(),
            ['submissionId' => $submission->getId()]
        );
        $filesUrl = $this->apiUrl(
            'datasets/' . $study->getId() . '/files',
            ['submissionId' => $submission->getId()]
        );
        $fileActionUrl = $this->apiUrl(
            'datasets/' . $study->getId() . '/file',
            ['submissionId' => $submission->getId()]
        );

        return [
            'studyId' => $study->getId(),
            'persistentUri' => $study->getPersistentUri(),
            'dataset' => $dataset->getAllData(),
            'datasetIsPublished' => $datasetIsPublished,
            'datasetInReview' => $this->isDatasetInReview($dataverseClient, $dataset),
            'citation' => $this->getCitation($dataverseClient, $study, $datasetIsPublished),
            'datasetUrl' => $datasetUrl,
            'publishConfirmMessage' => __('plugins.generic.dataverse.modal.confirmDatasetPublish', [
                'serverName' => $dataverseClient->getDataverseCollectionActions()->getRoot()->getName(),
                'serverUrl' => $serverUrl,
            ]),
            'forms' => [
                'datasetMetadata' => (new DatasetMetadataForm($datasetUrl, 'PUT', $dataset, 'workflow'))->getConfig(),
                'deleteDataset' => (new DeleteDatasetForm(
                    $datasetUrl,
                    $this->getRequest()->getContext(),
                    $this->getDeleteDatasetEmailBody($submission, $dataverseClient)
                ))->getConfig(),
            ],
            'filesListPanel' => $this->getFilesListPanelConfig(
                $filesUrl,
                $fileActionUrl,
                $this->getDatasetFileProps($dataset, $study)
            ),
        ];
    }

    private function getDatasetFileProps(Dataset $dataset, DataverseStudy $study): array
    {
        return array_map(function ($datasetFile) use ($study) {
            $props = $datasetFile->getVars();
            $props['downloadUrl'] = $this->apiUrl('datasets/' . $study->getId() . '/file', [
                'submissionId' => $study->getSubmissionId(),
                'fileId' => $datasetFile->getId(),
                'fileName' => $datasetFile->getFileName(),
            ]);
            return $props;
        }, $dataset->getFiles());
    }

    private function getFilesListPanelConfig(string $fileListUrl, string $fileActionUrl, array $items): array
    {
        return (new DatasetFilesListPanel(
            'datasetFiles',
            __('plugins.generic.dataverse.researchData.files'),
            [
                'addFileLabel' => __('plugins.generic.dataverse.addResearchData'),
                'fileListUrl' => $fileListUrl,
                'fileActionUrl' => $fileActionUrl,
                'items' => $items,
                'addFileModalTitle' => __('plugins.generic.dataverse.modal.addFile.title'),
                'title' => __('plugins.generic.dataverse.researchData'),
            ]
        ))->getConfig();
    }

    private function isDatasetInReview(DataverseClient $dataverseClient, Dataset $dataset): bool
    {
        $locks = $dataverseClient->getDatasetActions()->getDatasetLocks($dataset->getDatasetId());

        foreach ($locks as $lock) {
            if ($lock['lockType'] == 'InReview') {
                return true;
            }
        }

        return false;
    }

    private function getCitation(DataverseClient $dataverseClient, DataverseStudy $study, bool $datasetIsPublished): string
    {
        $citationData = $dataverseClient
            ->getDatasetActions()
            ->getCitation($study->getPersistentId(), $datasetIsPublished);

        return $citationData['citation'];
    }

    private function getDeleteDatasetEmailBody(Submission $submission, DataverseClient $dataverseClient): string
    {
        $request = $this->getRequest();
        $dataStatementUrl = $request->getDispatcher()->url(
            $request,
            Application::ROUTE_PAGE,
            $request->getContext()->getPath(),
            'dashboard',
            'mySubmissions',
            null,
            ['workflowSubmissionId' => $submission->getId()]
        );

        return __('emails.datasetDeleteNotification.body', [
            'submissionTitle' => $submission->getCurrentPublication()->getLocalizedFullTitle(null, 'html'),
            'dataStatementUrl' => $dataStatementUrl,
            'dataverseName' => $dataverseClient->getDataverseCollectionActions()->get()->getName(),
        ]);
    }

    private function userHasRole(int $roleId): bool
    {
        $request = $this->getRequest();
        $user = $request->getUser();

        return $user && $user->hasRole([$roleId], $request->getContext()->getId());
    }

    private function apiUrl(string $handlerPath, array $params = []): string
    {
        $request = $this->getRequest();

        return $request->getDispatcher()->url(
            $request,
            Application::ROUTE_API,
            $request->getContext()->getPath(),
            $handlerPath,
            null,
            null,
            $params
        );
    }
}
