<?php

import('plugins.generic.dataverse.classes.adapters.SubmissionAdapter');
import('plugins.generic.dataverse.classes.adapters.AuthorAdapter');

class SubmissionAdapterCreator
{
    public function createSubmissionAdapter($submission): SubmissionAdapter
    {
        $journalDao = DAORegistry::getDAO('JournalDAO');
        $journal = $journalDao->getById($submission->getContextId());
        $locale = $submission->getLocale();
        $publication = $submission->getCurrentPublication();

        $title = $publication->getLocalizedData('title', $locale);
        $authors = $this->retrieveAuthors($publication, $locale);
        $description = $publication->getLocalizedData('abstract', $locale);
        $keywords = $publication->getData('keywords')[$locale];
        $citation = $this->createSubmissionCitationAPA($submission, $journal);
        $reference = array($citation, array());

        return new SubmissionAdapter($title, $authors, $description, $keywords, $reference);
    }

    private function retrieveAuthors($publication, $locale)
    {
        $authors =  $publication->getData('authors');
        $authorAdapters = [];

        foreach ($authors as $author) {
            $givenName = $author->getLocalizedGivenName($locale);
            $familyName = $author->getLocalizedFamilyName($locale);
            $fullName = $author->getFullName($locale);
            $affiliation = $author->getLocalizedData('affiliation', $locale);
            $email = $author->getData('email');

            $affiliation = !is_null($affiliation) ? $affiliation : "";
            $email = !is_null($email) ? $email : "";
            $authorAdapters[] = new AuthorAdapter($givenName, $familyName, $fullName, $affiliation, $email);
        }

        return $authorAdapters;
    }

    private function retrievePubIdAttributes($submission) 
    {
        $contextId = $submission->getContextId();

        $pubIdAttributes = array();
        $pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $contextId);
        if (isset($pubIdPlugins['doipubidplugin'])) {
            $doiPlugin = $pubIdPlugins['doipubidplugin'];

            $pubId = $submission->getStoredPubId($doiPlugin->getPubIdType());
            if(isset($pubId)) {
                $pubIdAttributes['holdingsURI'] = $doiPlugin->getResolvingURL($contextId, $pubId);
                $pubIdAttributes['agency'] = $doiPlugin->getDisplayName();
                $pubIdAttributes['IDNo'] = $pubId;
            }
        }

        return $pubIdAttributes;
    }

    private function getAuthorCitation($author) 
    {
        return $author->getFamilyName() . ', ' . substr($author->getGivenName(), 0, 1) . ".";
    }

    public function createAuthorsCitationAPA($authors) 
    {
        $authorsCitation = '';
        $authorsNumbers = count($authors);

        if ($authorsNumbers > 5) {
            $authorsCitation .= $this->getAuthorCitation($authors[0]) . ' et al.';
        } else {
            foreach ($authors as $key => $author) {
                if($key == 0) {
                    $authorsCitation .= $this->getAuthorCitation($author);
                }
                if($authorsNumbers > 1) {
                    if ($key != 0 && $key < ($authorsNumbers - 1)) {
                        $authorsCitation .= ', ' . $this->getAuthorCitation($author);
                    } 
                    if ($key == ($authorsNumbers - 1)) {
                        $authorsCitation .= ', &amp; ' . $this->getAuthorCitation($author);
                    }    
                }
            }
        }

        return $authorsCitation;
    }

    public function createSubmissionCitationAPA($submission, $journal)
    {
        $authors = $this->retrieveAuthors($submission->getCurrentPublication(), $submission->getLocale());
        $submittedDate = new DateTime($submission->getDateSubmitted());
        
        $submissionCitation = $this->createAuthorsCitationAPA($authors) . ' ';
        $submissionCitation .= '(' . date_format($submittedDate, 'Y') . '). ';
        $submissionCitation .= '<em>' . $submission->getLocalizedTitle($submission->getLocale()) . '</em>. ';
        $submissionCitation .= $journal->getLocalizedName();
        

        return $submissionCitation;
    }

}
