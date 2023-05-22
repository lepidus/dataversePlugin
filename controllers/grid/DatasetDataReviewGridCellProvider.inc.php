<?php

import('lib.pkp.classes.controllers.grid.GridCellProvider');

class DatasetDataReviewGridCellProvider extends GridCellProvider
{
    public function getTemplateVarsFromRowColumn($row, $column): array
    {
        $element = $row->getData();
        $columnId = $column->getId();
        switch ($columnId) {
            case 'label':
                return [
                    'label' => $element->getFileName()
                ];
        }
        return parent::getTemplateVarsFromRowColumn($row, $column);
    }
}
