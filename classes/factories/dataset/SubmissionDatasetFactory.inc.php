<?php

import('plugins.generic.dataverse.classes.factories.dataset.DatasetDataFactory');
import('plugins.generic.dataverse.classes.entities.DatasetData');

class SubmissionDatasetFactory extends DatasetDataFactory
{
    private $submission;

    public function __construct(SubmissionAdapter $submission)
    {
        $this->submission = $submission;
    }

    protected function createDatasetData(): void
    {
        $submissionData = $this->submission->getAllData();
        $props = $this->sanitizeProps($submissionData);

        $dataset = new DatasetData();
        $dataset->setAllData($props);

        $this->dataset = $dataset;
    }

    private function sanitizeProps(array $submissionData): array
    {
        $authors = array();
        $contacts = array();
        foreach ($submissionData['authors'] as $author) {
            $authors[] = array(
                'givenName' => $author->getGivenName(),
                'familyName' => $author->getFamilyName(),
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
