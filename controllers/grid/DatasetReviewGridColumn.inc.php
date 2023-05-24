<?php

import('lib.pkp.classes.controllers.grid.GridColumn');
import('lib.pkp.classes.controllers.grid.GridCellProvider');
import('lib.pkp.classes.linkAction.request.RedirectAction');

class DatasetReviewGridColumn extends GridColumn
{
    private $study;
    
    public function __construct(DataverseStudy $study)
    {
        $this->study = $study;
        parent::__construct('label', 'common.name', null, null, new GridCellProvider());
    }

    function getCellActions($request, $row, $position = GRID_ACTION_POSITION_DEFAULT) {
        $cellActions = parent::getCellActions($request, $row, $position);
		$datasetFile = $row->getData();

        $context = $request->getContext();
        $downloadUrl = $request->getDispatcher()->url(
            $request, ROUTE_API, $context->getPath(), 'datasets/' . $this->study->getId() . '/file', null, null,
            ['fileId' => $datasetFile->getId(), 'filename' => $datasetFile->getFileName()]
        );

		$cellActions[] = new LinkAction(
            'downloadDatasetFile',
            new RedirectAction($downloadUrl),
            $datasetFile->getFileName()
        );

		return $cellActions;
	}
}
