<?php

import('plugins.generic.dataverse.classes.factories.dataset.DatasetFactory');
import('plugins.generic.dataverse.classes.entities.Dataset');

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
        $props['title'] = $submissionData['title'];
        $props['description'] = $submissionData['abstract'];
        $props['subject'] = $submissionData['subject'];
        $props['keywords'] = $submissionData['keywords'];
        $props['pubCitation'] = $submissionData['citation'];
        $props['contact'] = $submissionData['contact'];

        $props['authors'] = array_map(function (AuthorAdapter $author) {
            return new DatasetAuthor(
                $author->getFullName(),
                $author->getAffiliation(),
                $author->getOrcid()
            );
        }, $submissionData['authors']);

        return $props;
    }
}
