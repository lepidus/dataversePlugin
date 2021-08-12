<?php

class SubmissionAdapterCreator
{
    private $locale = "en_US";

    public function createSubmissionAdapter($submissionId, $authors): SubmissionAdapter
    {
        $submission = DAORegistry::getDAO('SubmissionDAO')->getById($submissionId);
        $publication = $submission->getCurrentPublication();

        $title = $publication->getLocalizedData('title', $this->locale);
        $description = $publication->getLocalizedData('abstract', $this->locale);
        $keywords = $publication->getData('keywords')[$this->locale];

        return new SubmissionAdapter($title, $authors, $description, $keywords);
    }
}
