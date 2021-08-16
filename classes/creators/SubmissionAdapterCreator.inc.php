<?php

import('plugins.generic.dataverse.classes.adapters.SubmissionAdapter');
import('plugins.generic.dataverse.classes.adapters.AuthorAdapter');

class SubmissionAdapterCreator
{
    public function createSubmissionAdapter($submission, $locale): SubmissionAdapter
    {
        $publication = $submission->getCurrentPublication();

        $title = $publication->getLocalizedData('title', $locale);
        $authors = $this->retrieveAuthors($publication, $locale);
        $description = $publication->getLocalizedData('abstract', $locale);
        $keywords = $publication->getData('keywords')[$locale];

        return new SubmissionAdapter($title, $authors, $description, $keywords);
    }

    private function retrieveAuthors($publication, $locale)
    {
        $authors =  $publication->getData('authors');
        $authorAdapters = [];

        foreach ($authors as $author) {
            $fullName = $author->getFullName($locale);
            $affiliation = $author->getLocalizedData('affiliation', $locale);
            $email = $author->getData('email');

            $affiliation = !is_null($affiliation) ? $affiliation : "";
            $email = !is_null($email) ? $email : "";
            $authorAdapters[] = new AuthorAdapter($fullName, $affiliation, $email);
        }

        return $authorAdapters;
    }

}
