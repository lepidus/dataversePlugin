<?php

import('plugins.generic.dataverse.classes.study.DataverseDatasetData');

class DataverseDatasetDataCreator
{
    private $metadata = [
        'datasetTitle' => 'title',
        'datasetDescription' => 'dsDescription',
        'datasetKeywords' => 'keyword'
    ];

    public function createDatasetData($metadataBlocks): DataverseDatasetData
    {
        $datasetData = new DataverseDatasetData();

        foreach ($metadataBlocks as $metadata) {
            if (!in_array($metadata->typeName, $this->metadata)){
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

    public function createMetadataObject($typeName, $value): stdClass
    {
        $objName = $this->metadata[$typeName] . 'Value';
        $obj = new stdClass();
        $obj->$objName = new stdClass();
        $obj->$objName->typeName = $objName;
        $obj->$objName->multiple = false;
        $obj->$objName->typeClass = 'primitive';
        $obj->$objName->typeName = $value;

        return $obj;
    }

    public function updataMetadataBlocks($metadataBlocks, $metadata): stdClass
    {
        foreach ($metadata as $key => $value) {
            foreach ($metadataBlocks->citation->fields as $obj) {
                if ($obj->typeName == $this->metadata[$key]) {
                    if (gettype($obj->value) == 'array') {
                        foreach ($obj->value as $class) {
                            $obj->value[] = $this->createMetadataObject($key, $value);
                        }
                    }
                    else {
                        $obj->value = $value;
                    }
                }
                elseif ($obj->typeName == 'subject' && in_array('N/A', $obj->value)) {
                    $obj->value = ['Other'];
                }
            }
        }

        $datasetMetadata = new stdClass();
        $datasetMetadata->metadataBlocks = $metadataBlocks;

        return $datasetMetadata;
    }
}