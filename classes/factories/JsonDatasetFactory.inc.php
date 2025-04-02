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

    private function getCurrentDatasetVersion()
    {
        $datasetVersions = (json_decode($this->jsonContent))->data;
        $currentVersion = $datasetVersions[0];

        if (count($datasetVersions) > 1) {
            foreach ($datasetVersions as $version) {
                if ($version->versionState == 'RELEASED') {
                    $currentVersion = $version;
                    break;
                }
            }
        }

        return $currentVersion;
    }

    protected function sanitizeProps(): array
    {
        $datasetVersion = $this->getCurrentDatasetVersion();
        $datasetData = $datasetVersion->metadataBlocks->citation->fields;

        $props = [];
        $props['persistentId'] = $datasetVersion->datasetPersistentId;
        $props['versionState'] = $datasetVersion->versionState;

        //We use only the license name (instead of name and uri) to
        //maintain compatibility with previous versions of Dataverse
        if (isset($datasetVersion->license->name)) {
            $props['license'] = $datasetVersion->license->name;
        }

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
                            isset($author->authorIdentifierScheme->value) ?
                                $author->authorIdentifierScheme->value
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

            if (!mb_check_encoding($file->label, 'UTF-8')) {
                $datasetFile->setFileName(mb_convert_encoding($file->label, 'UTF-8'));
            }
            if (!mb_check_encoding($file->dataFile->filename, 'UTF-8')) {
                $datasetFile->setOriginalFileName(mb_convert_encoding($file->dataFile->filename, 'UTF-8'));
            }

            return $datasetFile;
        }, $datasetVersion->files);

        return $props;
    }
}
