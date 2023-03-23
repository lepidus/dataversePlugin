<?php

import('plugins.generic.dataverse.classes.dataverseAPI.packagers.DatasetPackager');

class NativeAPIDatasetPackager extends DatasetPackager
{
    private $packageDirPath;

    private $datasetMetadata = [];

    private $files = [];

    public function __construct(Dataset $dataset)
    {
        $this->packageDirPath = tempnam('/tmp', 'dataverse');
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
                    switch (gettype($value)) {
                        case 'object':
                            $metadataField['value'][] = $this->createMultiCompoundMetadata($metadataField, $value);
                            break;
                        case 'array':
                            foreach ($value as $item) {
                                if (is_object($item)) {
                                    $metadataField['value'][] = $this->createMultiCompoundMetadata($metadataField, $item);
                                } else {
                                    $metadataField['value'][] = $this->createSimpleCompoundMetadata($metadataField, $item);
                                }
                            }
                            break;
                        case 'string':
                            $metadataField['value'][] = $this->createSimpleCompoundMetadata($metadataField, $value);
                            break;
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

    private function createSimpleCompoundMetadata(array $metadataField, string $value): array
    {
        $typeName = $metadataField['typeName'] == 'publication'
                ? $metadataField['typeName'] . 'Citation'
                : $metadataField['typeName'] . 'Value';

        return [
            $typeName => [
                'typeName' =>  $typeName,
                'multiple' => false,
                'typeClass' => 'primitive',
                'value' => $value
            ]
        ];
    }

    private function createMultiCompoundMetadata(array $metadataField, object $object): array
    {
        $objectData = $object->getAllData();

        $metadataValue = [];
        foreach ($objectData as $attr => $value) {
            $metadataValue = array_merge($metadataValue, [
                $metadataField['typeName'] . ucfirst($attr) => [
                    'typeName' =>  $metadataField['typeName'] . ucfirst($attr),
                    'multiple' => false,
                    'typeClass' => 'primitive',
                    'value' => $value
                ]
            ]);
        }
        return $metadataValue;
    }

    public function createDatasetPackage(): void
    {
        $this->loadMetadata();

        $datasetContent = [];
        if (is_null($this->dataset->getPersistentId())) {
            $datasetContent['datasetVersion']['metadataBlocks']['citation']['fields'] = $this->getDatasetMetadata();
        } else {
            $datasetContent['fields'] = $this->getDatasetMetadata();
        }

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

    public function clear(): void
    {
        if (file_exists($this->getPackagePath())) {
            unlink($this->getPackagePath());
        }
        rmdir($this->getPackageDirPath());
    }
}
