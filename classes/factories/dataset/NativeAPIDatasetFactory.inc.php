<?php

import('plugins.generic.dataverse.classes.factories.dataset.DatasetFactory');

class NativeAPIDatasetFactory extends DatasetFactory
{
    private $response;

    public function __construct(DataverseResponse $response)
    {
        $this->response = $response;
    }

    protected function sanitizeProps(): array
    {
        $responseData = json_decode($this->response->getData());
        $datasetData = $responseData->datasetVersion->metadataBlocks->citation->fields;

        $props = [];
        foreach ($datasetData as $metadata) {
            if ($metadata->typeClass == 'primitive') {
                $props[$metadata->typeName] = $metadata->value;
            }
            switch ($metadata->typeName) {
                case 'author':
                    $props['authors'] = array_map(function (stdClass $author) {
                        return new DatasetAuthor(
                            $author->authorName->value,
                            $author->authorAffiliation->value,
                            $author->authorIdentifier->value
                        );
                    }, $metadata->value);
                    break;
                case 'datasetContact':
                    $contact = $metadata->value[0];
                    $props['contact'] = new DatasetContact(
                        $contact->datasetContactName->value,
                        $contact->datasetContactEmail->value,
                        $contact->datasetContactAffiliation->value
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

        $citation = str_replace(
            $responseData->persistentUrl,
            '<a href="' . $responseData->persistentUrl . '">' .
                $responseData->persistentUrl .
            '</a>',
            $responseData->datasetVersion->citation
        );
        $props['citation'] = $citation;

        return $props;
    }
}
