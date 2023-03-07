<?php

import('plugins.generic.dataverse.classes.dataverseAPI.packagers.DatasetPackager');

class NativeAPIDatasetPackager extends DatasetPackager
{
    private $packageDirPath;

    private $datasetMetadata = [];

    private $files = [];


    public function __construct(Dataset $dataset)
    {
        $this->packageDirPath = tempnam(TEMPORARY_FILES_DIR, 'dataverse');
        unlink($this->packageDirPath);
        mkdir($this->packageDirPath);
        parent::__construct($dataset);
    }

    public function getPackageDirPath(): string
    {
        return $this->packageDirPath;
    }

    public function getDatasetMetadata(): array
    {
        return $this->datasetMetadata;
    }

    public function loadMetadata(): void
    {
        $datasetData = $this->dataset->getAllData();

        foreach ($datasetData as $attr => $value) {
            $metadataField = $this->getMetadataField($attr);
            if (empty($metadataField)) {
                continue;
            }
            switch($metadataField['typeClass']) {
                case 'primitive':
                    $metadataField['value'] = $value;
                    break;
                case 'compound':
                    if (is_object($value)) {
                        $metadataField['value'] = $this->createMultiCompoundMetadata($metadataField, $value);
                    } else {
                        if (!is_array($value)) {
                            $value = [$value];
                        }
                        $metadataField['value'] = $this->createSimpleCompoundMetadata($metadataField, $value);
                    }
                    break;
                case 'controlledVocabulary':
                    $metadataField['value'] = [$value];
                    break;
            }
            $this->datasetMetadata[] = $metadataField;
        }
    }

    public function getMetadataField(string $metadata): array
    {
        $fields = [
            'title' => [
                'typeName' => 'title',
                'multiple' => false,
                'typeClass' => 'primitive'
            ],
            'authors' => [
                'typeName'=> 'author',
                'multiple'=> true,
                'typeClass'=> 'compound'
            ],
            'description' => [
                'typeName' => 'dsDescription',
                'multiple' => true,
                'typeClass' => 'compound'
            ],
            'keywords' => [
                'typeName' => 'keyword',
                'multiple' => true,
                'typeClass' => 'compound'
            ],
            'subject' => [
                'typeName' => 'subject',
                'multiple' => true,
                'typeClass' => 'controlledVocabulary'
            ],
            'contact' => [
                'typeName' => 'datasetContact',
                'multiple' => true,
                'typeClass' => 'compound'
            ],
            'depositor' => [
                'typeName' => 'depositor',
                'multiple' => false,
                'typeClass' => 'primitive'
            ],
            'pubCitation' => [
                'typeName' => 'publication',
                'multiple' => true,
                'typeClass' => 'compound'
            ]
        ];

        return isset($fields[$metadata]) ? $fields[$metadata] : [];
    }

    private function createSimpleCompoundMetadata(array $metadataField, array $value): array
    {
        return array_map(function (string $value) use ($metadataField) {
            $typeName = $metadataField['typeName'] == 'publication'
                ? $metadataField['typeName'] . 'Citation'
                : $metadataField['typeName'] . 'Value';
            return [
                'typeName' =>  $typeName,
                'multiple' => false,
                'typeClass' => 'primitive',
                'value' => $value
            ];
        }, $value);
    }

    private function createMultiCompoundMetadata(array $metadataField, object $object): array
    {
        $objectData = $object->getAllData();

        return array_map(function (string $attr, string $value) use ($metadataField) {
            return [
                'typeName' =>  $metadataField['typeName'] . ucfirst($attr),
                'multiple' => false,
                'typeClass' => 'primitive',
                'value' => $value
            ];
        }, array_keys($objectData), $objectData);
    }

    public function createDatasetPackage(): void
    {
        $this->loadMetadata();
        $datasetContent = [
            'datasetVersion' => [
                'metadataBlocks' => [
                    'citation' => [
                        'displayName' => 'Citation Metadata',
                        'fields' => $this->getDatasetMetadata()
                    ]
                ]
            ]
        ];

        $datasetPackage = fopen($this->getPackagePath(), 'w');
        fwrite($datasetPackage, json_encode($datasetContent));
        fclose($datasetPackage);
    }

    public function createFilesPackage(): void
    {
        foreach ($this->dataset->getFiles() as $file) {
            $this->files[$fileName] = $filePath;
        }
    }

    public function getPackagePath(): string
    {
        return $this->getPackageDirPath() . DIRECTORY_SEPARATOR . '/dataset.json';
    }
}
