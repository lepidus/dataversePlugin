<?php

namespace APP\plugins\generic\dataverse\classes\dispatchers;

use PKP\plugins\Hook;
use APP\core\Application;
use APP\template\TemplateManager;
use PKP\file\TemporaryFileManager;
use APP\plugins\generic\dataverse\classes\components\listPanel\DatasetFilesListPanel;
use APP\plugins\generic\dataverse\classes\dispatchers\DataverseDispatcher;
use APP\plugins\generic\dataverse\classes\services\DataStatementService;
use APP\plugins\generic\dataverse\classes\DraftDatasetFilesValidator;
use APP\plugins\generic\dataverse\classes\facades\Repo;

class DraftDatasetFilesDispatcher extends DataverseDispatcher
{
    protected function registerHooks(): void
    {
        Hook::add('Template::SubmissionWizard::Section', [$this, 'addDraftDatasetFilesSection']);
        Hook::add('TemplateManager::display', [$this, 'addToFilesStep']);
        Hook::add('Template::SubmissionWizard::Section::Review', [$this, 'addToReviewStep']);
        Hook::add('Submission::validateSubmit', [$this, 'validateSubmissionFields']);
    }

    public function addDraftDatasetFilesSection(string $hookName, array $params)
    {
        $submission = $params[0]['submission'];
        $templateMgr = $params[1];
        $output = &$params[2];

        $output .= $templateMgr->fetch($this->plugin->getTemplateResource('draftDatasetFiles.tpl'));
    }

    public function addToFilesStep(string $hookName, array $params)
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $templateMgr = $params[0];

        if ($request->getRequestedPage() !== 'submission' || $request->getRequestedOp() === 'saved') {
            return false;
        }

        $submission = $request
            ->getRouter()
            ->getHandler()
            ->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        if (!$submission || !$submission->getData('submissionProgress')) {
            return false;
        }

        $this->addDatasetFilesList($templateMgr, $request, $submission);
        $addGalleyLabel = __('submission.upload.uploadFiles');

        $steps = $templateMgr->getState('steps');
        $steps = array_map(function ($step) use ($addGalleyLabel) {
            if ($step['id'] === 'files') {
                $step['sections'][] = [
                    'id' => 'datasetFiles',
                    'name' => __('plugins.generic.dataverse.researchData'),
                    'description' => __('plugins.generic.dataverse.researchDataDescription', ['addGalleyLabel' => $addGalleyLabel]),
                    'type' => 'datasetFiles',
                ];
            }
            return $step;
        }, $steps);

        $templateMgr->setState(['steps' => $steps]);

        return false;
    }

    private function addDatasetFilesList($templateMgr, $request, $submission): void
    {
        $items = $this->getDatasetFiles($request, $submission->getId());
        $fileListApiUrl = $request
            ->getDispatcher()
            ->url($request, Application::ROUTE_API, $request->getContext()->getPath(), 'draftDatasetFiles', null, null, ['submissionId' => $submission->getId()]);
        $fileActionApiUrl = $request
            ->getDispatcher()
            ->url($request, Application::ROUTE_API, $request->getContext()->getPath(), 'draftDatasetFiles');

        $datasetFilesListPanel = new DatasetFilesListPanel(
            'datasetFiles',
            __('plugins.generic.dataverse.researchData.files'),
            $submission,
            [
                'addFileLabel' => __('plugins.generic.dataverse.addResearchData'),
                'fileListUrl' => $fileListApiUrl,
                'fileActionUrl' => $fileActionApiUrl,
                'items' => $items,
                'modalTitle' => __('plugins.generic.dataverse.modal.addFile.title'),
                'title' => __('plugins.generic.dataverse.researchData'),
            ]
        );

        $wizardComponents = $templateMgr->getState('components');
        $wizardComponents[$datasetFilesListPanel->id] = $datasetFilesListPanel->getConfig();

        $templateMgr->addJavaScript(
            'dataset-files-list-panel',
            $this->plugin->getPluginFullPath() . '/js/ui/components/DatasetFilesListPanel.js',
            [
                'priority' => TemplateManager::STYLE_SEQUENCE_LAST,
                'contexts' => ['backend']
            ]
        );

        $templateMgr->setState([
            'components' => $wizardComponents,
        ]);
    }

    private function getDatasetFiles($request, $submissionId): array
    {
        $draftDatasetFiles = Repo::draftDatasetFile()->getBySubmissionId($submissionId)->toArray();
        $datasetFilesApiUrl = $request
            ->getDispatcher()
            ->url($request, Application::ROUTE_API, $request->getContext()->getPath(), "draftDatasetFiles");
        $datasetFilesProps = [];

        foreach ($draftDatasetFiles as $draftDatasetFile) {
            $props = $draftDatasetFile->getAllData();
            $props['downloadUrl'] = $datasetFilesApiUrl . '/' . $draftDatasetFile->getId() . '/download';
            $datasetFilesProps[] = $props;
        }
        ksort($datasetFilesProps);

        return $datasetFilesProps;
    }

    public function addToReviewStep(string $hookName, array $params): bool
    {
        $step = $params[0]['step'];
        $templateMgr = $params[1];
        $output = &$params[2];

        if ($step === 'files') {
            $output .= $templateMgr->fetch($this->plugin->getTemplateResource('review/draftDatasetFiles.tpl'));
        }

        return false;
    }

    public function validateSubmissionFields(string $hookName, array $params)
    {
        $errors = &$params[0];
        $submission = $params[1];
        $publication = $submission->getCurrentPublication();

        $dataStatementTypes = $publication->getData('dataStatementTypes');

        if (in_array(DataStatementService::DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED, $dataStatementTypes)) {
            $draftDatasetFiles = Repo::draftDatasetFile()->getBySubmissionId($submission->getId())->toArray();

            if (empty($draftDatasetFiles)) {
                $errors['datasetFiles'] = [__('plugins.generic.dataverse.error.researchData.required')];
            } elseif ($this->validateGalleyContainsResearchData($submission, $draftDatasetFiles)) {
                $errors['datasetFiles'] = [__('plugins.generic.dataverse.notification.galleyContainsResearchData')];
            }
        }

        return false;
    }

    private function validateGalleyContainsResearchData($submission, $draftDatasetFiles): bool
    {
        $submissionFiles = Repo::submissionFile()
            ->getCollector()
            ->filterBySubmissionIds([$submission->getId()])
            ->getMany()
            ->toArray();

        if (empty($submissionFiles)) {
            return false;
        }

        $temporaryFileManager = new TemporaryFileManager();
        $datasetFiles = array_map(function ($draftFile) use ($temporaryFileManager) {
            return $temporaryFileManager->getFile(
                $draftFile->getData('fileId'),
                $draftFile->getData('userId')
            );
        }, $draftDatasetFiles);

        $validator = new DraftDatasetFilesValidator();
        return $validator->galleyContainsResearchData($submissionFiles, $datasetFiles);
    }
}
