<?php

import('lib.pkp.classes.controllers.grid.GridHandler');

class DraftDatasetFileGridHandler extends GridHandler
{
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_AUTHOR],
            ['fetchGrid', 'fetchRow', 'addFile', 'uploadFile', 'saveFile', 'deleteFile']
        );
    }

    public function getSubmission(): Submission
    {
        return $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
    }

    public function getPublication(): Publication
    {
        return $this->getAuthorizedContextObject(ASSOC_TYPE_PUBLICATION);
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
        import('lib.pkp.classes.security.authorization.WorkflowStageAccessPolicy');
        $this->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', WORKFLOW_STAGE_ID_PRODUCTION));

        import('lib.pkp.classes.security.authorization.PublicationAccessPolicy');
        $this->addPolicy(new PublicationAccessPolicy($request, $args, $roleAssignments));

        return parent::authorize($request, $args, $roleAssignments);
    }

    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        $this->setTitle('plugins.generic.dataverse.researchData');

        $router = $request->getRouter();
        import('lib.pkp.classes.linkAction.request.AjaxModal');
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
        import('plugins.generic.dataverse.classes.file.DraftDatasetFileDAO');
        $draftDatasetFileDAO = new DraftDatasetFileDAO();
        $draftDatasetFiles = $draftDatasetFileDAO->getBySubmissionId($this->getSubmission()->getId());

        $researchDataFiles = [];
        foreach ($draftDatasetFiles as $draftDatasetFile) {
            $researchDataFiles[$draftDatasetFile->getId()] = $draftDatasetFile;
        }
        return $researchDataFiles;
    }

    protected function getRowInstance()
    {
        import('plugins.generic.dataverse.controllers.grid.DraftDatasetFileGridRow');
        return new DraftDatasetFileGridRow(
            $this->getSubmission(),
            $this->getPublication()
        );
    }

    public function getFileNameColumn(): GridColumn
    {
        import('plugins.generic.dataverse.controllers.grid.DraftDatasetFileGridCellProvider');
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

        import('plugins.generic.dataverse.controllers.grid.form.DraftDatasetFileForm');
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

        import('lib.pkp.classes.file.TemporaryFileManager');
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
            import('plugins.generic.dataverse.classes.file.DraftDatasetFileDAO');
            $draftDatasetFileDAO = new DraftDatasetFileDAO();
            $draftDatasetFileDAO->deleteById($fileId);

            import('lib.pkp.classes.log.SubmissionLog');
            import('lib.pkp.classes.log.SubmissionFileEventLogEntry');
            \SubmissionLog::logEvent(
                $request,
                $this->getSubmission(),
                SUBMISSION_LOG_FILE_UPLOAD,
                'plugins.generic.dataverse.log.researchDataFileDeleted',
                ['filename' => $args['fileName']]
            );

            return DAO::getDataChangedEvent();
        }

        return new JSONMessage(false);
    }
}
