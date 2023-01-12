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
        $props = array();
        $props['title'] = $submissionData['title'];
        $props['description'] = $submissionData['abstract'];
        $props['subject'] = $submissionData['subject'];
        $props['keywords'] = $submissionData['keywords'] ?? null;
        $props['citation'] = $submissionData['citation'];

        $props['authors'] = array_map(function (AuthorAdapter $author) {
            return array(
                'authorName' => $author->getFullName(),
                'affiliation' => $author->getAffiliation(),
                'identifier' => $author->getOrcid()
            );
        }, $submissionData['authors']);

        if (!empty($submissionData['contact'])) {
            $props['contacts'] = array($submissionData['contact']);
        } else {
            $props['contacts'] = array_map(function (AuthorAdapter $author) {
                return array(
                    'name' => $author->getFullName(),
                    'email' => $author->getEmail(),
                    'affiliation' => $author->getAffiliation()
                );
            }, $submissionData['authors']);
        }

        return $props;
    }
}
