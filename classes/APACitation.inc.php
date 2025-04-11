<?php

class APACitation
{
    private $locale;

    public function getCitationAsMarkupByStudy(DataverseStudy $study): string
    {
        $href = '<a href="'. $study->getPersistentUri() .'">'. $study->getPersistentUri() .'</a>';
        return str_replace($study->getPersistentUri(), $href, strip_tags($study->getDataCitation()));
    }

    public function getFormattedCitationBySubmission(Submission $submission): string
    {
        $this->locale = $submission->getLocale();
        $journalDao = DAORegistry::getDAO('JournalDAO');
        $journal = $journalDao->getById($submission->getContextId());
        $publication = $submission->getCurrentPublication();
        $authors =  $publication->getData('authors');
        $submittedDate = new DateTime($submission->getDateSubmitted());
        $submissionDoi = $this->getSubmissionDoi($submission);

        $submissionCitation = $this->createAuthorsCitationAPA($authors) . ' ';
        $submissionCitation .= '(' . date_format($submittedDate, 'Y') . '). ';
        $submissionCitation .= '<em>' . $submission->getLocalizedTitle($submission->getLocale()) . '</em>. ';
        $submissionCitation .= $journal->getLocalizedName();

        if ($submissionDoi) {
            $submissionCitation .= ". <a href=\"$submissionDoi\">$submissionDoi</a>";
        }

        return $submissionCitation;
    }

    public function createAuthorsCitationAPA(array $authors): string
    {
        $authorsCitation = '';
        $authorsNumbers = count($authors);

        if ($authorsNumbers > 5) {
            $authorsCitation .= $this->getAuthorCitation($authors[0]) . ' et al.';
        } else {
            foreach ($authors as $key => $author) {
                if ($key == 0) {
                    $authorsCitation .= $this->getAuthorCitation($author);
                }
                if ($authorsNumbers > 1) {
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

    private function getAuthorCitation(Author $author): string
    {
        $familyName = $author->getLocalizedFamilyName($this->locale);
        $givenName = $author->getLocalizedGivenName($this->locale);

        if (is_array($familyName)) {
            $familyName = $familyName[$this->locale];
        }
        if (is_array($givenName)) {
            $givenName = $givenName[$this->locale];
        }

        return $familyName . ', ' . mb_substr($givenName, 0, 1) . ".";
    }

    private function getSubmissionDoi(Submission $submission): string
    {
        $publication = $submission->getCurrentPublication();
        $doi = $publication->getStoredPubId('doi');

        if ($doi) {
            return 'https://doi.org/' . $this->doiURLEncode($doi);
        }

        return '';
    }

    private function doiURLEncode($pubId)
    {
        $search = ['%', '"', '#', ' ', '<', '>', '{'];
        $replace = ['%25', '%22', '%23', '%20', '%3c', '%3e', '%7b'];
        $pubId = str_replace($search, $replace, $pubId);
        return $pubId;
    }
}
