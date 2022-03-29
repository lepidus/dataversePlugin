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

        $submissionCitation = $this->createAuthorsCitationAPA($authors) . ' ';
        $submissionCitation .= '(' . date_format($submittedDate, 'Y') . '). ';
        $submissionCitation .= '<em>' . $submission->getLocalizedTitle($submission->getLocale()) . '</em>. ';
        $submissionCitation .= $journal->getLocalizedName();

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

    private function getAuthorCitation(Author $author): string
    {
        return $author->getLocalizedFamilyName($this->locale) . ', ' . mb_substr($author->getLocalizedGivenName($this->locale), 0, 1) . ".";
    }

    private function retrievePubIdAttributes(Submission $submission): array
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
}
