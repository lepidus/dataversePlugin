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

    protected function createDataset(): void
    {
        $submissionData = $this->submission->getAllData();
        $props = $this->sanitizeProps($submissionData);

        $dataset = new Dataset();
        $dataset->setAllData($props);

        $this->dataset = $dataset;
    }

    private function sanitizeProps(array $submissionData): array
    {
        $authors = array();
        $contacts = array();
        foreach ($submissionData['authors'] as $author) {
            $authors[] = array(
                'authorName' => $author->getFullName(),
                'affiliation' => $author->getAffiliation(),
                'identifier' => $author->getOrcid() ?? null
            );
            $contacts[] = array(
                'name' => $author->getFullName(),
                'email' => $author->getEmail()
            );
        }

        $props = array();
        $props['title'] = $submissionData['title'];
        $props['description'] = $submissionData['abstract'];
        $props['subject'] = $submissionData['subject'];
        $props['keywords'] = $submissionData['keywords'];
        $props['citation'] = $submissionData['citation'];
        $props['authors'] = $authors;
        $props['contacts'] = $contacts;

        return $props;
    }
}
