<?php

import('plugins.generic.dataverse.classes.factories.dataset.DatasetFactory');
import('plugins.generic.dataverse.classes.entities.Dataset');
import('plugins.generic.dataverse.classes.entities.DatasetAuthor');
import('plugins.generic.dataverse.classes.entities.DatasetFile');

class SubmissionDatasetFactory extends DatasetFactory
{
    private $submission;

    public function __construct(SubmissionAdapter $submission)
    {
        $this->submission = $submission;
    }

    protected function sanitizeProps(): array
    {
        $submissionData = $this->submission->getAllData();

        $props = array();
        foreach ($submissionData as $attr => $value) {
            switch ($attr) {
                case 'abstract':
                    $props['description'] = $value;
                    break;
                case 'citation':
                    $props['pubCitation'] = $value;
                    break;
                case 'authors':
                    $props['authors'] = array_map(function (AuthorAdapter $author) {
                        return new DatasetAuthor(
                            $author->getFullName(),
                            $author->getAffiliation(),
                            $author->getOrcid()
                        );
                    }, $value);
                    break;
                case 'files':
                    $props['files'] = array_map(function (TemporaryFile $file) {
                        $datasetFile = new DatasetFile();
                        $datasetFile->setOriginalFileName($file->getOriginalFileName());
                        $datasetFile->setPath($file->getFilePath());
                        return $datasetFile;
                    }, $value);
                    break;
                default:
                    $props[$attr] = $value ?? null;
                    break;
            }
        }

        return $props;
    }
}
