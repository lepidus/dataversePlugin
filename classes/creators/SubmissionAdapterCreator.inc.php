<?php

import('plugins.generic.dataverse.classes.adapters.SubmissionAdapter');
import('plugins.generic.dataverse.classes.adapters.AuthorAdapter');
import('plugins.generic.dataverse.classes.entities.DatasetContact');
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
        $abstract = $publication->getLocalizedData('abstract', $locale);
        $subject = $submission->getData('datasetSubject');
        $keywords = $publication->getData('keywords')[$locale] ?? null;
        $citation = $apaCitation->getFormattedCitationBySubmission($submission);
        $authors = $this->retrieveAuthors($publication, $locale);
        $files = $this->retrieveFiles($id);
        $contact = $this->retrieveContact($publication);

        $adapter = new SubmissionAdapter();
        $adapter->setRequiredData($id, $title, $abstract, $subject, $keywords, $citation, $contact, $authors, $files);

        return $adapter;
    }

    private function retrieveAuthors(Publication $publication): array
    {
        $authors =  $publication->getData('authors');
        $authorAdapters = [];

        foreach ($authors as $author) {
            $givenName = $author->getLocalizedGivenName();
            $familyName = $author->getLocalizedFamilyName();
            $affiliation = $author->getLocalizedData('affiliation');
            $email = $author->getData('email');
            $orcid = $author->getOrcid();
            $orcidNumber = null;

            if (preg_match('/.{4}-.{4}-.{4}-.{4}/', $orcid, $matches)) {
                $orcidNumber = $matches[0];
            }

            $affiliation = !is_null($affiliation) ? $affiliation : "";
            $email = !is_null($email) ? $email : "";

            $authorAdapters[] = new AuthorAdapter($givenName, $familyName, $affiliation, $email, $orcidNumber);
        }

        return $authorAdapters;
    }

    private function retrieveContact(Publication $publication): DatasetContact
    {
        $primaryAuthor = $publication->getPrimaryAuthor();
        if (!empty($primaryAuthor)) {
            $name = $primaryAuthor->getFullName(false, true);
            $email = $primaryAuthor->getEmail();
            $affiliation = $primaryAuthor->getLocalizedData('affiliation');
        } else {
            $request = Application::get()->getRequest();
            $user = $request->getUser();
            $name = $user->getFullName(false, true);
            $email = $user->getEmail();
            $affiliation = $user->getLocalizedData('affiliation');
        }
        return new DatasetContact($name, $email, $affiliation);
    }

    private function retrieveFiles(int $submissionId): array
    {
        $draftDatasetFileDAO = DAORegistry::getDAO('DraftDatasetFileDAO');
        $draftDatasetFiles = $draftDatasetFileDAO->getBySubmissionId($submissionId);
        return $draftDatasetFiles;
    }
}
