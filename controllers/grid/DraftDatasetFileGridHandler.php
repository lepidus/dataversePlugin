<?php

namespace APP\plugins\generic\dataverse\controllers\grid;

use PKP\controllers\grid\GridHandler;
use PKP\controllers\grid\GridColumn;
use APP\core\Application;
use PKP\security\Role;
use PKP\security\authorization\PublicationAccessPolicy;
use PKP\security\authorization\WorkflowStageAccessPolicy;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\core\JSONMessage;
use PKP\plugins\PluginRegistry;
use PKP\file\TemporaryFileManager;
use PKP\db\DAO;
use PKP\core\Core;
use APP\log\event\SubmissionEventLogEntry;
use APP\plugins\generic\dataverse\controllers\grid\DraftDatasetFileGridRow;
use APP\plugins\generic\dataverse\controllers\grid\DraftDatasetFileGridCellProvider;
use APP\plugins\generic\dataverse\controllers\grid\form\DraftDatasetFileForm;
use APP\plugins\generic\dataverse\classes\facades\Repo;

class DraftDatasetFileGridHandler extends GridHandler
{
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR],
            ['fetchGrid', 'fetchRow', 'addFile', 'uploadFile', 'saveFile', 'deleteFile']
        );
    }

    public function getSubmission()
    {
        return $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
    }

    public function getPublication()
    {
        return $this->getAuthorizedContextObject(Application::ASSOC_TYPE_PUBLICATION);
    }

    public function getRequestArgs(): array
    {
        return [
            'submissionId' => $this->getSubmission()->getId(),
            'publicationId' => $this->getPublication()->getId(),
        ];
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', WORKFLOW_STAGE_ID_PRODUCTION));
        $this->addPolicy(new PublicationAccessPolicy($request, $args, $roleAssignments));

        return parent::authorize($request, $args, $roleAssignments);
    }

    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        $this->setTitle('plugins.generic.dataverse.researchData');

        $router = $request->getRouter();
        $this->addAction(
            new LinkAction(
                'addFile',
                new AjaxModal(
                    $router->url($request, null, null, 'addFile', null, $this->getRequestArgs()),
                    __('plugins.generic.dataverse.modal.addFile.title'),
                    'modal_add_item'
                ),
                __('plugins.generic.dataverse.addResearchData'),
                'add_item'
            )
        );

        $this->addColumn($this->getFileNameColumn());
    }

    protected function loadData($request, $filter)
    {
        $draftDatasetFiles = Repo::draftDatasetFile()->getBySubmissionId($this->getSubmission()->getId());
        $researchDataFiles = [];

        foreach ($draftDatasetFiles as $draftDatasetFile) {
            $researchDataFiles[$draftDatasetFile->getId()] = $draftDatasetFile;
        }

        return $researchDataFiles;
    }

    protected function getRowInstance()
    {
        return new DraftDatasetFileGridRow(
            $this->getSubmission(),
            $this->getPublication()
        );
    }

    public function getFileNameColumn(): GridColumn
    {
        return new GridColumn(
            'label',
            'common.name',
            null,
            null,
            new DraftDatasetFileGridCellProvider()
        );
    }

    public function getNewFileForm(): DraftDatasetFileForm
    {
        $plugin = PluginRegistry::getPlugin('generic', 'dataverseplugin');
        $template = $plugin->getTemplateResource('form/draftDatasetFileForm.tpl');

        return new DraftDatasetFileForm(
            $template,
            $this->getSubmission()->getId(),
            $this->getPublication()->getId()
        );
    }

    public function addFile($args, $request)
    {
        $form = $this->getNewFileForm();
        $form->initData();
        return new JSONMessage(true, $form->fetch($request));
    }

    public function uploadFile($args, $request)
    {
        $user = $request->getUser();

        $temporaryFileManager = new TemporaryFileManager();
        $temporaryFile = $temporaryFileManager->handleUpload('uploadedFile', $user->getId());
        if ($temporaryFile) {
            $json = new JSONMessage(true);
            $json->setAdditionalAttributes(array(
                'temporaryFileId' => $temporaryFile->getId()
            ));
            return $json;
        } else {
            return new JSONMessage(false, __('common.uploadFailed'));
        }
    }

    public function saveFile($args, $request)
    {
        $form = $this->getNewFileForm();
        $form->readInputData();

        if ($form->validate()) {
            $fileId = $form->execute();

            return DAO::getDataChangedEvent();
        }

        return new JSONMessage(false);
    }

    public function deleteFile($args, $request)
    {
        $fileId = isset($args['fileId']) ? $args['fileId'] : null;
        $context = $request->getContext();

        if ($request->checkCSRF() && $fileId) {
            $submission = $this->getSubmission();
            $draftDatasetFile = Repo::draftDatasetFile()->get($fileId);
            Repo::draftDatasetFile()->delete($draftDatasetFile);

            $researchDataLog = Repo::eventLog()->newDataObject([
                'assocType' => Application::ASSOC_TYPE_SUBMISSION,
                'assocId' => $submission->getId(),
                'eventType' => SubmissionEventLogEntry::SUBMISSION_LOG_FILE_UPLOAD,
                'message' => __('plugins.generic.dataverse.log.researchDataFileDeleted', ['filename' => $args['fileName']]),
                'isTranslated' => true,
                'dateLogged' => Core::getCurrentDate(),
            ]);
            Repo::eventLog()->add($researchDataLog);

            return DAO::getDataChangedEvent();
        }

        return new JSONMessage(false);
    }
}
