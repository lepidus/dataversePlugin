<?php

import('plugins.generic.dataverse.classes.factories.DatasetFactory');
import('plugins.generic.dataverse.classes.entities.DatasetAuthor');
import('plugins.generic.dataverse.classes.entities.DatasetContact');
import('plugins.generic.dataverse.classes.entities.DatasetFile');

class JsonDatasetFactory extends DatasetFactory
{
    private $jsonContent;

    public function __construct(string $jsonContent)
    {
        $this->jsonContent = $jsonContent;
    }

    protected function sanitizeProps(): array
    {
        $responseData = json_decode($this->jsonContent);
        $datasetVersion = $responseData->data->latestVersion;
        $datasetData = $datasetVersion->metadataBlocks->citation->fields;

        $props = [];
        $props['persistentId'] = $datasetVersion->datasetPersistentId;
        $props['versionState'] = $datasetVersion->versionState;
        $props['license'] = $datasetVersion->license->name;
        foreach ($datasetData as $metadata) {
            if ($metadata->typeClass == 'primitive') {
                $props[$metadata->typeName] = $metadata->value;
            }
            switch ($metadata->typeName) {
                case 'author':
                    $props['authors'] = array_map(function (stdClass $author) {
                        return new DatasetAuthor(
                            $author->authorName->value,
                            isset($author->authorAffiliation->value) ?
                                $author->authorAffiliation->value
                                : null,
                            isset($author->authorIdentifier->value) ?
                                $author->authorIdentifier->value
                                : null
                        );
                    }, $metadata->value);
                    break;
                case 'datasetContact':
                    $contact = $metadata->value[0];
                    $props['contact'] = new DatasetContact(
                        $contact->datasetContactName->value,
                        isset($contact->datasetContactEmail->value) ?
                            $contact->datasetContactEmail->value
                            : null,
                        isset($contact->datasetContactAffiliation->value) ?
                            $contact->datasetContactAffiliation->value
                            : null
                    );
                    break;
                case 'dsDescription':
                    $props['description'] = $metadata->value[0]->dsDescriptionValue->value;
                    break;
                case 'subject':
                    $props['subject'] = $metadata->value[0];
                    break;
                case 'keyword':
                    $props['keywords'] = array_map(function (stdClass $keyword) {
                        return $keyword->keywordValue->value;
                    }, $metadata->value);
                    break;
                case 'publication':
                    $props['pubCitation'] = $metadata->value[0]->publicationCitation->value;
                    break;
                default:
                    break;
            }
        }

        $props['files'] = array_map(function (stdClass $file) {
            $datasetFile = new DatasetFile();
            $datasetFile->setId($file->dataFile->id);
            $datasetFile->setFileName($file->label);
            $datasetFile->setOriginalFileName($file->dataFile->filename);
            return $datasetFile;
        }, $datasetVersion->files);

        return $props;
    }
}
