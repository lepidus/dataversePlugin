<?php

namespace APP\plugins\generic\dataverse\controllers\grid;

use APP\core\Application;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridCellProvider;
use PKP\controllers\grid\GridHandler;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\RedirectAction;
use APP\plugins\generic\dataverse\classes\dataverseStudy\DataverseStudy;

class DatasetReviewGridColumn extends GridColumn
{
    private $study;

    public function __construct(?DataverseStudy $study)
    {
        $this->study = $study;
        parent::__construct('label', 'common.name', null, null, new GridCellProvider());
    }

    public function getCellActions($request, $row, $position = GridHandler::GRID_ACTION_POSITION_DEFAULT)
    {
        $cellActions = parent::getCellActions($request, $row, $position);

        if(!is_null($this->study)) {
            $datasetFile = $row->getData();

            error_log(print_r($datasetFile, true));

            $context = $request->getContext();
            $downloadUrl = $request->getDispatcher()->url(
                $request,
                Application::ROUTE_API,
                $context->getPath(),
                'datasets/' . $this->study->getId() . '/file',
                null,
                null,
                ['fileId' => $datasetFile->getId(), 'fileName' => $datasetFile->getFileName()]
            );

            $cellActions[] = new LinkAction(
                'downloadDatasetFile',
                new RedirectAction($downloadUrl),
                $datasetFile->getFileName()
            );
        }

        return $cellActions;
    }
}
