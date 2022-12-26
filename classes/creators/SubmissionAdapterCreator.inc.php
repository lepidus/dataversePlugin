<?php

import('plugins.generic.dataverse.classes.adapters.SubmissionAdapter');
import('plugins.generic.dataverse.classes.adapters.AuthorAdapter');
import('plugins.generic.dataverse.classes.APACitation');

class SubmissionAdapterCreator
{
    public function createSubmissionAdapter(Submission $submission): SubmissionAdapter
    {
        $locale = $submission->getLocale();
        $publication = $submission->getCurrentPublication();
        $apaCitation = new APACitation();
        $id = $submission->getId();
        $title = $publication->getLocalizedData('title', $locale);
        $authors = $this->retrieveAuthors($publication, $locale);
        $files = $this->retrieveFiles($id);
        $description = $publication->getLocalizedData('abstract', $locale);
        $keywords = $publication->getData('keywords')[$locale];
        $citation = $apaCitation->getFormattedCitationBySubmission($submission);
        $reference = array($citation, array());
        $subject = $submission->getData('datasetSubject');

        return new SubmissionAdapter($id, $title, $authors, $files, $description, $keywords, $reference, $subject);
    }

    private function retrieveAuthors(Publication $publication, string $locale): array
    {
        $authors =  $publication->getData('authors');
        $authorAdapters = [];

        foreach ($authors as $author) {
            $givenName = $author->getLocalizedGivenName($locale);
            $familyName = $author->getLocalizedFamilyName($locale);
            $affiliation = $author->getLocalizedData('affiliation', $locale);
            $email = $author->getData('email');
            $orcid = $author->getOrcid();

            preg_match('/.{4}-.{4}-.{4}-.{4}/', $orcid, $matches);
            $orcidNumber = $matches[0];

            $affiliation = !is_null($affiliation) ? $affiliation : "";
            $email = !is_null($email) ? $email : "";

            $authorAdapters[] = new AuthorAdapter($givenName, $familyName, $affiliation, $email, $orcidNumber);
        }

        return $authorAdapters;
    }

    private function retrieveFiles(int $submissionId): array
    {
        $draftDatasetFileDAO = DAORegistry::getDAO('DraftDatasetFileDAO');
        $draftDatasetFiles = $draftDatasetFileDAO->getBySubmissionId($submissionId);
        return $draftDatasetFiles;
    }
}
