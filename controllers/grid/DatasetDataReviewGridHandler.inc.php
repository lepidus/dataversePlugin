<?php

import('lib.pkp.controllers.grid.files.fileList.FileListGridHandler');

class DatasetDataReviewGridHandler extends FileListGridHandler
{
    public function __construct()
    {
        import('plugins.generic.dataverse.controllers.grid.DatasetDataReviewGridDataProvider');
        parent::__construct(
            new DatasetDataReviewGridDataProvider(),
            null
        );

        $this->addRoleAssignment(
            array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_REVIEWER),
            array('fetchGrid', 'fetchRow')
        );

        $this->setTitle('plugins.generic.dataverse.researchData');
    }
}
