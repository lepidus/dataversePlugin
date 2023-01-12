<?php

import('plugins.generic.dataverse.classes.study.DataverseDatasetData');
import('plugins.generic.dataverse.classes.DataverseMetadata');

class DataverseDatasetDataCreator
{
    public function createDatasetData($metadataBlocks): DataverseDatasetData
    {
        $datasetData = new DataverseDatasetData();
        $metadataFields = ['title', 'dsDescription', 'keyword', 'subject'];

        foreach ($metadataBlocks as $metadata) {
            if (!in_array($metadata->typeName, $metadataFields)) {
                continue;
            }
            if (is_array($metadata->value)) {
                $values = [];
                foreach ($metadata->value as $value) {
                    if ($metadata->typeName == 'subject') {
                        $datasetData->setData($metadata->typeName, $value);
                    } else {
                        $attr = $metadata->typeName . 'Value';
                        $values[] = $value->$attr->value;
                        $datasetData->setData($metadata->typeName, $values);
                    }
                }
            } else {
                $datasetData->setData($metadata->typeName, $metadata->value);
            }
        }

        return $datasetData;
    }

    public function getMetadata($metadataBlocks, $metadata)
    {
        foreach ($metadataBlocks as $field) {
            if ($field->typeName == $metadata) {
                return $field->value;
            }
        }

        return null;
    }

    public function createMetadataFields($metadata): stdClass
    {
        $datasetMetadata = new stdClass();
        $datasetMetadata->fields = [];

        foreach ($metadata as $key => $values) {
            $datasetMetadata->fields[] = $this->createMetadataObject($key, $values);
        }

        return $datasetMetadata;
    }

    public function createMetadataObject($typeName, $values): stdClass
    {
        $metadataAttr = DataverseMetadata::getMetadataAttributes($typeName);

        $metadata = new stdClass();
        $metadata->typeName = $metadataAttr['typeName'];
        $metadata->multiple = $metadataAttr['multiple'];
        $metadata->typeClass = $metadataAttr['typeClass'];

        if ($metadataAttr['multiple'] && !is_array($values)) {
            $values = [$values];
        }

        if ($metadataAttr['typeClass'] == 'compound') {
            $metadata->value = [];
            foreach ($values as $value) {
                if ($metadataAttr['typeName'] == 'author') {
                    $metadata->value[] = $this->createAuthorObject($value);
                } else {
                    $metadata->value[] = $this->createCompoundObject($metadataAttr['typeName'], $value);
                }
            }
        } else {
            $metadata->value = $values;
        }

        return $metadata;
    }

    public function createCompoundObject($typeName, $value): stdClass
    {
        $valueName = $typeName . 'Value';

        $obj = new stdClass();
        $obj->$valueName = new stdClass();
        $obj->$valueName->typeName = $valueName;
        $obj->$valueName->multiple = false;
        $obj->$valueName->typeClass = 'primitive';
        $obj->$valueName->value = $value;

        return $obj;
    }

    public function createAuthorObject(array $author): array
    {
        $authorProps = DataverseMetadata::retrieveAuthorProps($author);
        return $authorProps;
    }
}
