<?php

namespace APP\plugins\generic\dataverse\dataverseAPI\packagers;

use APP\plugins\generic\dataverse\dataverseAPI\packagers\DatasetPackager;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use APP\plugins\generic\dataverse\classes\DataverseMetadata;
use APP\plugins\generic\dataverse\classes\entities\Dataset;

class NativeAPIDatasetPackager extends DatasetPackager
{
    private $packageDirPath;
    private $dataverseMetadata;
    private $datasetLicense;
    private $datasetMetadata = [];
    private $files = [];

    public function __construct(Dataset $dataset)
    {
        $this->dataverseMetadata = new DataverseMetadata();
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

    public function loadPackageData(): void
    {
        $datasetData = $this->dataset->getAllData();

        if (isset($datasetData['license'])) {
            $this->datasetLicense = $datasetData['license'];
        }

        foreach ($datasetData as $attr => $value) {
            $metadataField = $this->getMetadataField($attr);
            if (empty($metadataField) || is_null($value)) {
                continue;
            }
            switch ($metadataField['typeClass']) {
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
                'typeName' => 'author',
                'multiple' => true,
                'typeClass' => 'compound'
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
            'relatedPublication' => [
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
        $controlledVocabMetadata = ['identifierScheme', 'IDType'];

        $metadataValue = [];
        foreach ($objectData as $attr => $value) {
            if (is_null($value)) {
                continue;
            }

            $metadataTypeClass = in_array($attr, $controlledVocabMetadata) ? 'controlledVocabulary' : 'primitive';
            $metadataValue = array_merge($metadataValue, [
                $metadataField['typeName'] . ucfirst($attr) => [
                    'typeName' =>  $metadataField['typeName'] . ucfirst($attr),
                    'multiple' => false,
                    'typeClass' => $metadataTypeClass,
                    'value' => $value
                ]
            ]);
        }
        return $metadataValue;
    }

    public function createDatasetPackage(): void
    {
        $this->loadPackageData();

        $datasetContent = [];

        //We use only the license name (instead of name and uri) to
        //maintain compatibility with previous versions of Dataverse
        if (!is_null($this->datasetLicense)) {
            $datasetContent['license'] = $this->datasetLicense;
        }
        $datasetContent['metadataBlocks']['citation']['fields'] = $this->getDatasetMetadata();

        $this->addAdditionalMetadata($datasetContent);

        if (is_null($this->dataset->getPersistentId())) {
            $datasetContent = ['datasetVersion' => $datasetContent];
        }

        $datasetPackage = fopen($this->getPackagePath(), 'w');
        fwrite($datasetPackage, json_encode($datasetContent));
        fclose($datasetPackage);
    }

    private function addAdditionalMetadata(array &$datasetContent): void
    {
        $datasetData = $this->dataset->getAllData();

        $dataverseClient = new DataverseClient();
        $requiredMetadata = $dataverseClient->getDataverseCollectionActions()->getRequiredMetadata();

        foreach ($requiredMetadata as $block) {
            $this->initializeMetadataBlock($datasetContent, $block);

            foreach ($block['fields'] as $field) {
                $this->addFieldToMetadataBlock($datasetContent, $block['name'], $field, $datasetData);
            }
        }
    }

    private function initializeMetadataBlock(array &$datasetContent, array $block): void
    {
        if (!isset($datasetContent['metadataBlocks'][$block['name']])) {
            $datasetContent['metadataBlocks'][$block['name']] = [
                'displayName' => $block['displayName'],
                'fields' => []
            ];
        }
    }

    private function addFieldToMetadataBlock(array &$datasetContent, string $blockName, array $field, array $datasetData): void
    {
        if ($field['typeClass'] === 'compound') {
            $this->addCompoundField($datasetContent, $blockName, $field, $datasetData);
            return;
        }

        $this->addSimpleField($datasetContent, $blockName, $field, $datasetData);
    }

    private function addCompoundField(array &$datasetContent, string $blockName, array $field, array $datasetData): void
    {
        $fieldKey = array_search(
            $field['name'],
            array_column($datasetContent['metadataBlocks'][$blockName]['fields'], 'typeName')
        );

        if ($fieldKey === false) {
            $this->createNewCompoundField($datasetContent, $blockName, $field, $datasetData);
        } else {
            $this->updateExistingCompoundField($datasetContent, $blockName, $field, $fieldKey, $datasetData);
        }
    }

    private function createNewCompoundField(array &$datasetContent, string $blockName, array $field, array $datasetData): void
    {
        $childFieldValues = $this->buildChildFieldValues($field['childFields'], $datasetData);

        if ($field['multiple']) {
            $childFieldValues = [$childFieldValues];
        }

        $datasetContent['metadataBlocks'][$blockName]['fields'][] = [
            'typeName' => $field['name'],
            'multiple' => $field['multiple'],
            'typeClass' => $field['typeClass'],
            'value' => $childFieldValues
        ];
    }

    private function updateExistingCompoundField(array &$datasetContent, string $blockName, array $field, int $fieldKey, array $datasetData): void
    {
        $currentFieldValue = &$datasetContent['metadataBlocks'][$blockName]['fields'][$fieldKey]['value'];
        $newChildFields = $this->buildChildFieldValues($field['childFields'], $datasetData);

        if ($field['multiple']) {
            $currentFieldValue[0] = array_merge($currentFieldValue[0], $newChildFields);
        } else {
            $currentFieldValue = array_merge($currentFieldValue, $newChildFields);
        }
    }

    private function buildChildFieldValues(array $childFields, array $datasetData): array
    {
        $childFieldValues = [];
        foreach ($childFields as $childField) {
            if (!isset($datasetData[$childField['name']])) {
                continue;
            }

            $childFieldValues[$childField['name']] = [
                'typeName' => $childField['name'],
                'multiple' => $childField['multiple'],
                'typeClass' => $childField['typeClass'],
                'value' => $datasetData[$childField['name']]
            ];
        }
        return $childFieldValues;
    }

    private function addSimpleField(array &$datasetContent, string $blockName, array $field, array $datasetData): void
    {
        if (!isset($datasetData[$field['name']])) {
            return;
        }

        $datasetContent['metadataBlocks'][$blockName]['fields'][] = [
            'typeName' => $field['name'],
            'multiple' => $field['multiple'],
            'typeClass' => $field['typeClass'],
            'value' => $datasetData[$field['name']]
        ];
    }

    public function createFilesPackage(): void
    {
        foreach ($this->dataset->getFiles() as $file) {
            $this->files[$fileName] = $filePath;
        }
    }

    public function getPackagePath(): string
    {
        return $this->getPackageDirPath() . DIRECTORY_SEPARATOR . 'dataset.json';
    }

    public function clear(): void
    {
        if (file_exists($this->getPackagePath())) {
            unlink($this->getPackagePath());
        }
        rmdir($this->getPackageDirPath());
    }
}
