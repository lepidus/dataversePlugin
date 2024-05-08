<?php

namespace APP\plugins\generic\dataverse\classes\dispatchers;

use PKP\plugins\Hook;
use APP\core\Application;
use APP\template\TemplateManager;
use APP\plugins\generic\dataverse\classes\components\listPanel\DatasetFilesListPanel;
use APP\plugins\generic\dataverse\classes\dispatchers\DataverseDispatcher;
use APP\plugins\generic\dataverse\classes\services\DataStatementService;
use APP\plugins\generic\dataverse\classes\facades\Repo;

class DraftDatasetFilesDispatcher extends DataverseDispatcher
{
    protected function registerHooks(): void
    {
        Hook::add('Template::SubmissionWizard::Section', [$this, 'addDraftDatasetFilesSection']);
        Hook::add('TemplateManager::display', [$this, 'addToFilesStep']);
        // HookRegistry::register('submissionsubmitstep2form::display', array($this, 'addDraftDatasetFileContainer'));
        // HookRegistry::register('submissionsubmitstep2form::validate', array($this, 'addStep2Validation'));
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
        $draftDatasetFiles = Repo::draftDatasetFile()->getBySubmissionId($submission->getId())->toArray();
        $fileListApiUrl = $request
            ->getDispatcher()
            ->url($request, Application::ROUTE_API, $request->getContext()->getPath(), 'draftDatasetFiles', null, null, ['submissionId' => $submission->getId()]);

        $items = array_map(function ($draftDatasetFile) {
            return $draftDatasetFile->getAllData();
        }, $draftDatasetFiles);
        ksort($items);

        $items = [['id' => 128, 'fileName' => 'barao de cote.zip']]; //only for tests

        $datasetFilesListPanel = new DatasetFilesListPanel(
            'datasetFiles',
            __('plugins.generic.dataverse.researchData.files'),
            [
                'addFileLabel' => __('plugins.generic.dataverse.addResearchData'),
                'apiUrl' => $fileListApiUrl,
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
            'deleteDatasetFileLabel' => __('plugins.generic.dataverse.modal.deleteDatasetFile'),
            'confirmDeleteMessage' => __('plugins.generic.dataverse.modal.confirmDelete')
        ]);
    }

    public function addDraftDatasetFileContainer(string $hookName, array $params): ?string
    {
        $form = $params[0];
        $output = &$params[1];
        $publication = $form->submission->getCurrentPublication();

        $request = PKPApplication::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);

        $requestArgs = $templateMgr->get_template_vars('requestArgs');
        if (empty($requestArgs)) {
            $requestArgs = [
                'submissionId' => $form->submission->getId(),
                'publicationId' => $publication->getId(),
            ];
            $templateMgr->assign('requestArgs', $requestArgs);
        }

        $dataStatementTypes = $publication->getData('dataStatementTypes');
        if (empty($dataStatementTypes) || !in_array(DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED, $dataStatementTypes)) {
            return $output;
        }

        $templateOutput = $templateMgr->fetch($form->_template);
        $pattern = '/<div[^>]+class="section formButtons form_buttons[^>]+>/';
        if (preg_match($pattern, $templateOutput, $matches, PREG_OFFSET_CAPTURE)) {
            $offset = $matches[0][1];
            $output = substr($templateOutput, 0, $offset);
            $output .= $templateMgr->fetch($this->plugin->getTemplateResource('draftDatasetFile.tpl'));
            $output .= substr($templateOutput, $offset);
        }

        return $output;
    }

    public function addStep2Validation(string $hookName, array $params): void
    {
        $form = &$params[0];
        $publication = $form->submission->getCurrentPublication();

        if (!empty($publication->getData('dataStatementTypes'))) {
            $this->validateResearchDataFileRequired($form);
            $this->validateGalleyContainsResearchData($form);
        }
    }

    private function validateResearchDataFileRequired(SubmissionSubmitStep2Form $form): void
    {
        $publication = $form->submission->getCurrentPublication();

        if (!in_array(DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED, $publication->getData('dataStatementTypes'))) {
            return;
        }

        $draftDatasetFileDAO = DAORegistry::getDAO('DraftDatasetFileDAO');
        if (empty($draftDatasetFileDAO->getBySubmissionId($form->submission->getId()))) {
            $form->addError('dataverseStep2ValidationError', __("plugins.generic.dataverse.researchDataFile.error"));
            $form->addErrorField('dataverseStep2ValidationError');
        }
    }

    private function validateGalleyContainsResearchData(SubmissionSubmitStep2Form $form): void
    {
        $galleys = $form->submission->getGalleys();

        if (empty($galleys)) {
            return;
        }

        $galleyFiles = array_map(function (ArticleGalley $galley) {
            return Services::get('submissionFile')->get($galley->getFileId());
        }, $galleys);

        $draftDatasetFileDAO = DAORegistry::getDAO('DraftDatasetFileDAO');
        $draftDatasetFiles = $draftDatasetFileDAO->getBySubmissionId($form->submission->getId());

        import('lib.pkp.classes.file.TemporaryFileManager');
        $datasetFiles = array_map(function (DraftDatasetFile $draftFile) {
            $temporaryFileManager = new TemporaryFileManager();
            return $temporaryFileManager->getFile(
                $draftFile->getData('fileId'),
                $draftFile->getData('userId')
            );
        }, $draftDatasetFiles);

        import('plugins.generic.dataverse.classes.DraftDatasetFilesValidator');
        $validator = new DraftDatasetFilesValidator();
        if ($validator->galleyContainsResearchData($galleyFiles, $datasetFiles)) {
            $form->addError('dataverseStep2ValidationError', __("plugins.generic.dataverse.notification.galleyContainsResearchData"));
            $form->addErrorField('dataverseStep2ValidationError');
        }
    }
}
