<?php

class SubmissionAdapterCreator
{
    private $locale = "en_US";
    private $subject = "Testing Subject";

    public function createSubmissionAdapter($submissionId): SubmissionAdapter
    {
        $submission = DAORegistry::getDAO('SubmissionDAO')->getById($submissionId);
        $publication = $submission->getCurrentPublication();

        $title = $publication->getLocalizedData('title', $this->locale);
        $authors = $this->retrieveAuthors($publication);
        $description = $publication->getLocalizedData('abstract', $this->locale);

        $keywords = [$this->subject];

        return new SubmissionAdapter($title, $authors, $description, $keywords);
    }

    protected function retrieveAuthors($publication)
    {
        $authors =  $publication->getData('authors');
        $authorAdapters = [];

        foreach ($authors as $author) {
            $fullName = $author->getFullName($this->locale);
            $affiliation = $author->getLocalizedData('affiliation', $this->locale);

            $affiliation = !is_null($affiliation) ? $affiliation : "";
            $authorAdapters[] = new AuthorAdapter($fullName, $affiliation);
        }

        return $authorAdapters;
    }
}
