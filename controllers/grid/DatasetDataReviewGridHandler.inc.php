<?php

import('lib.pkp.classes.controllers.grid.GridHandler');
import('plugins.generic.dataverse.controllers.grid.DatasetDataReviewGridColumn');

class DatasetDataReviewGridHandler extends GridHandler
{
    private $study;
    
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_REVIEWER),
            array('fetchGrid', 'fetchRow')
        );
    }

    public function getSubmission(): Submission
    {
        return $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
    }

    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);
        
        $submissionId = $this->getSubmission()->getId();
        $dataverseStudyDao = DAORegistry::getDAO('DataverseStudyDAO');
        $this->study = $dataverseStudyDao->getStudyBySubmissionId($submissionId);

        $this->setTitle('plugins.generic.dataverse.researchData');
        $this->addColumn(new DatasetDataReviewGridColumn($this->study));
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
        $rowsData = [];

        if(!is_null($this->study)) {
            $dataverseClient = new DataverseClient();
            $dataset = $dataverseClient->getDatasetActions()->get($this->study->getPersistentId());

            foreach ($dataset->getFiles() as $datasetFile) {
                $rowsData[$datasetFile->getId()] = $datasetFile;
            }
        }

        return $rowsData;
    }
}
