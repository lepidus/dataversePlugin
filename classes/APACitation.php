<?php

namespace APP\plugins\generic\dataverse\classes;

use DateTime;
use APP\core\Application;
use APP\submission\Submission;
use APP\publication\Publication;
use APP\author\Author;
use PKP\db\DAORegistry;
use APP\plugins\generic\dataverse\classes\dataverseStudy\DataverseStudy;

class APACitation
{
    private $locale;

    public function getCitationAsMarkupByStudy(DataverseStudy $study): string
    {
        $href = '<a href="'. $study->getPersistentUri() .'">'. $study->getPersistentUri() .'</a>';
        return str_replace($study->getPersistentUri(), $href, strip_tags($study->getDataCitation()));
    }

    public function getFormattedCitationBySubmission(Submission $submission, ?Publication $publication = null): string
    {
        if (is_null($publication)) {
            $publication = $submission->getCurrentPublication();
        }

        $this->locale = $submission->getData('locale');
        $context = Application::getContextDAO()->getById($submission->getData('contextId'));
        $authors =  $publication->getData('authors')->toArray();
        $submittedDate = new DateTime($submission->getData('dateSubmitted'));
        $doiObject = $publication->getData('doiObject');

        $submissionCitation = $this->createAuthorsCitationAPA($authors) . ' ';
        $submissionCitation .= '(' . date_format($submittedDate, 'Y') . '). ';
        $submissionCitation .= '<em>' . $publication->getLocalizedTitle($this->locale) . '</em>. ';
        $submissionCitation .= $context->getLocalizedName();
        if ($doiObject) {
            $doiUrl = $doiObject->getResolvingUrl();
            $submissionCitation .= ". <a href=\"$doiUrl\">$doiUrl</a>";
        }

        return $submissionCitation;
    }

    public function createAuthorsCitationAPA(array $authors): string
    {
        $authorsCitation = '';
        $authors = array_values($authors);
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
}
