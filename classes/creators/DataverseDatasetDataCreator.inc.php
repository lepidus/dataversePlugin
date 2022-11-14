<?php

import('plugins.generic.dataverse.classes.study.DataverseDatasetData');

class DataverseDatasetDataCreator
{
    public function create($metadataBlocks): DataverseDatasetData
    {
        $datasetData = new DataverseDatasetData();

        $attrMapping = ['title', 'dsDescription', 'keyword'];

        foreach ($metadataBlocks as $metadata) {
            if (!in_array($metadata->typeName, $attrMapping)){
                continue;
            }
            if (gettype($metadata->value) == 'array') {
                foreach ($metadata->value as $value) {
                    $attr = $metadata->typeName . 'Value';
                    $datasetData->setData($metadata->typeName, $value->$attr->value);
                }
            }
            else {
                $datasetData->setData($metadata->typeName, $metadata->value);
            }
        }

        return $datasetData;
    }
}