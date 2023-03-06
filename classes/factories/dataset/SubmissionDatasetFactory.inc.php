<?php

import('plugins.generic.dataverse.classes.factories.dataset.DatasetFactory');
import('plugins.generic.dataverse.classes.entities.Dataset');
import('plugins.generic.dataverse.classes.entities.DatasetAuthor');
import('plugins.generic.dataverse.classes.entities.DatasetFile');

class SubmissionDatasetFactory extends DatasetFactory
{
    private $submission;

    public function __construct(Submission $submission)
    {
        $this->submission = $submission;
    }

    protected function sanitizeProps(): array
    {
        $publication = $this->submission->getCurrentPublication();

        $props = [];
        $props['title'] = $publication->getLocalizedTitle('title');
        $props['description'] = $publication->getLocalizedData('abstract');
        $props['keywords'] = $publication->getData('keywords');
        $props['subject'] = $this->submission->getData('datasetSubject');
        $props['authors'] = array_map([$this, 'createDatasetAuthor'], $this->submission->getAuthors());
        $props['contact'] = $this->createDatasetContact();
        $props['depositor'] = $this->getDatasetDepositor();
        $props['pubCitation'] = $this->getDatasetPubCitation();

        return $props;
    }

    private function createDatasetAuthor(Author $author): DatasetAuthor
    {
        return new DatasetAuthor(
            $author->getFullName(false, true),
            $author->getLocalizedData('affiliation'),
            $this->getAuthorOrcidNumber($author->getOrcid())
        );
    }

    private function getAuthorOrcidNumber(?string $orcid): ?string
    {
        return preg_match('/.{4}-.{4}-.{4}-.{4}/', $orcid, $matches) ? $matches[0] : null;
    }

    private function createDatasetContact(): DatasetContact
    {
        $primaryAuthor = $this->submission->getPrimaryAuthor();
        if (!empty($primaryAuthor)) {
            $name = $primaryAuthor->getFullName(false, true);
            $email = $primaryAuthor->getEmail();
            $affiliation = $primaryAuthor->getLocalizedData('affiliation');
        } else {
            $request = Application::get()->getRequest();
            $submissionUser = $request->getUser();
            $name = $submissionUser->getFullName(false, true);
            $email = $submissionUser->getEmail();
            $affiliation = $submissionUser->getLocalizedData('affiliation');
        }
        return new DatasetContact($name, $email, $affiliation);
    }

    private function getDatasetDepositor(): string
    {
        $request = Application::get()->getRequest();
        $submissionUser = $request->getUser();
        $userName = $submissionUser->getFullName(false, true);

        $context = DAORegistry::getDAO('JournalDAO')->getById($this->submission->getContextId());
        $contextName = $context->getLocalizedName();

        return $userName . ' (via ' . $contextName . ')';
    }

    private function getDatasetPubCitation(): string
    {
        import('plugins.generic.dataverse.classes.APACitation');
        $apaCitation = new APACitation();
        return $apaCitation->getFormattedCitationBySubmission($this->submission);
    }
}
