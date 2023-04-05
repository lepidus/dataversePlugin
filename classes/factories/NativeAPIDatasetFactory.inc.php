<?php

import('plugins.generic.dataverse.classes.factories.DatasetFactory');
import('plugins.generic.dataverse.classes.entities.DatasetAuthor');
import('plugins.generic.dataverse.classes.entities.DatasetContact');

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
        $datasetData = $responseData->data->fields;

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
                        $contact->datasetContactEmail->value,
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

        return $props;
    }
}
