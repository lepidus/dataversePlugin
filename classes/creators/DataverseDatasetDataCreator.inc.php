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
            if (is_array($metadata->value)) {
                $values = [];
                foreach ($metadata->value as $value) {
                    $attr = $metadata->typeName . 'Value';
                    $values[] = $value->$attr->value;

                }
                $datasetData->setData($metadata->typeName, $values);
            }
            else {
                $datasetData->setData($metadata->typeName, $metadata->value);
            }
        }

        return $datasetData;
    }

    public function createMetadataObjects($typeName, $values): stdClass
    {
        $field = new stdClass();
        $field->typeName = $typeName;
        $field->multiple = is_array($values);
        $field->typeClass = is_array($values) ? 'compound' : 'primitive';

        if (is_array($values)) {
            $objects = [];
            foreach ($values as $value) {
                $objects[] = $this->createValueObject($typeName, $value);
            }
            $field->value = $objects;
        }
        else {
            $field->value = $values;
        }        

        return $field;
    }

    private function createValueObject($typeName, $value): stdClass
    {
        $objName = $typeName . 'Value';
        $obj = new stdClass();
        $obj->$objName = new stdClass();
        $obj->$objName->typeName = $objName;
        $obj->$objName->multiple = false;
        $obj->$objName->typeClass = 'primitive';
        $obj->$objName->value = $value;

        return $obj;
    }

    public function updataMetadataBlocks($metadataBlocks, $metadata): stdClass
    {
        $metadataFields = [];
        foreach ($metadata as $key => $value) {
            if (!empty($value)) {
                $metadataFields[$key] = $this->createMetadataObjects($this->metadata[$key], $value);
            }
        }

        foreach ($metadata as $key => $data) {
            $hasMetadata = false;
            foreach ($metadataBlocks->citation->fields as $index => $field) {
                if ($field->typeName == $this->metadata[$key]) {
                    $hasMetadata = true;
                    $metadataKey = $index;
                }
            }
            if ($hasMetadata && !empty($data)) {
                $metadataBlocks->citation->fields[$metadataKey] = $metadataFields[$key];
            }
            elseif ($hasMetadata && empty($data)) {
                $field =& $metadataBlocks->citation->fields[$metadataKey];
                $multiple = $field->multiple;
                $fieldValue = $this->createValueObject($field->typeName, $data);

                $field->value = $multiple ? [$fieldValue] : $fieldValue;
            }
            elseif (!$hasMetadata && !empty($data)) {
                $metadataBlocks->citation->fields[] = $metadataFields[$key];
            }
        }
        $this->defineSubjectMetadata($metadataBlocks);

        $datasetMetadata = new stdClass();
        $datasetMetadata->metadataBlocks = $metadataBlocks;

        return $datasetMetadata;
    }

    private function defineSubjectMetadata(&$metadataBlocks): void
    {
        foreach ($metadataBlocks->citation->fields as $obj) {
            if ($obj->typeName == 'subject' && in_array('N/A', $obj->value)) {
                $obj->value = ['Other'];
            }
        }
    }
}