<?php

namespace APP\plugins\generic\dataverse\classes\factories;

use APP\plugins\generic\dataverse\classes\factories\DatasetFactory;
use APP\plugins\generic\dataverse\classes\entities\DatasetAuthor;
use APP\plugins\generic\dataverse\classes\entities\DatasetContact;
use APP\plugins\generic\dataverse\classes\entities\DatasetFile;
use stdClass;

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

            $encodedChar = 'Ã';
            if (str_contains($file->label, $encodedChar)) {
                $datasetFile->setFileName(utf8_decode($file->label));
            }
            if (str_contains($file->dataFile->filename, $encodedChar)) {
                $datasetFile->setOriginalFileName(utf8_decode($file->dataFile->filename));
            }

            return $datasetFile;
        }, $datasetVersion->files);

        return $props;
    }
}
