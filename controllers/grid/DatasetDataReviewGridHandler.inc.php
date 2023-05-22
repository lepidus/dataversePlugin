<?php

import('lib.pkp.classes.controllers.grid.GridHandler');

class DatasetDataReviewGridHandler extends GridHandler
{
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_REVIEWER),
            array('fetchGrid', 'fetchRow')
        );

        $this->setTitle('plugins.generic.dataverse.researchData');
        $this->addColumn($this->getFileNameColumn());
    }

    public function getSubmission(): Submission
    {
        return $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
    }
    
    public function getFileNameColumn(): GridColumn
    {
        import('plugins.generic.dataverse.controllers.grid.DatasetDataReviewGridCellProvider');
        return new GridColumn(
            'label',
            'common.name',
            null,
            null,
            new DatasetDataReviewGridCellProvider()
        );
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        $context = $request->getContext();
        import('lib.pkp.classes.security.authorization.SubmissionAccessPolicy');
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments, 'submissionId', !$context->getData('restrictReviewerFileAccess')));

        $stageId = $request->getUserVar('stageId');
        import('lib.pkp.classes.security.authorization.internal.WorkflowStageRequiredPolicy');
        $this->addPolicy(new WorkflowStageRequiredPolicy($stageId));

        import('lib.pkp.classes.security.authorization.internal.ReviewRoundRequiredPolicy');
        $this->addPolicy(new ReviewRoundRequiredPolicy($request, $args));

        import('lib.pkp.classes.security.authorization.internal.ReviewAssignmentRequiredPolicy');
        $this->addPolicy(new ReviewAssignmentRequiredPolicy($request, $args, 'reviewAssignmentId'));

        return parent::authorize($request, $args, $roleAssignments);
    }

    protected function loadData($request, $filter)
    {
        $submissionId = $this->getSubmission()->getId();
        $dataverseStudyDao = DAORegistry::getDAO('DataverseStudyDAO');
        $study = $dataverseStudyDao->getStudyBySubmissionId($submissionId);

        $researchDataFiles = [];

        if(!is_null($study)) {
            $dataverseClient = new DataverseClient();
            $dataset = $dataverseClient->getDatasetActions()->get($study->getPersistentId());

            foreach ($dataset->getFiles() as $datasetFile) {
                $researchDataFiles[$datasetFile->getId()] = $datasetFile;
            }
        }

        return $researchDataFiles;
    }
}
