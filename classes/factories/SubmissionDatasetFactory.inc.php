<?php

import('plugins.generic.dataverse.classes.factories.DatasetFactory');
import('plugins.generic.dataverse.classes.DataverseMetadata');
import('plugins.generic.dataverse.classes.entities.DatasetAuthor');
import('plugins.generic.dataverse.classes.entities.DatasetContact');
import('plugins.generic.dataverse.classes.entities.DatasetFile');
import('plugins.generic.dataverse.classes.entities.DatasetRelatedPublication');
import('plugins.generic.dataverse.classes.exception.MissingDepositFileException');

class SubmissionDatasetFactory extends DatasetFactory
{
    private $submission;
    /** @var bool */
    private $includeFiles;

    public function __construct(Submission $submission, bool $includeFiles = true)
    {
        $this->submission = $submission;
        $this->includeFiles = $includeFiles;
    }

    protected function sanitizeProps(): array
    {
        $publication = $this->submission->getCurrentPublication();
        $authors = $this->submission->getAuthors();

        $props = [];
        $datasetTitlePrefix = __('plugins.generic.dataverse.dataset.titlePrefix');
        $props['title'] = $datasetTitlePrefix . $publication->getLocalizedTitle('title');
        $props['description'] = $publication->getLocalizedData('abstract');
        $props['keywords'] = $publication->getLocalizedData('keywords');
        $props['language'] = $this->submission->getData('datasetLanguage');
        $props['subject'] = $this->submission->getData('datasetSubject');
        $props['license'] = $this->submission->getData('datasetLicense');
        $props['authors'] = array_map([$this, 'createDatasetAuthor'], $authors);
        $props['contact'] = $this->createDatasetContact();
        $props['depositor'] = $this->getDatasetDepositor();
        $props['dateOfDeposit'] = date('Y-m-d', time());
        $props['relatedPublication'] = $this->getDatasetRelatedPublication($publication);
        $props['files'] = $this->includeFiles ? $this->getDatasetFiles() : [];

        return $props;
    }

    private function createDatasetAuthor(Author $author): DatasetAuthor
    {
        return new DatasetAuthor(
            $author->getFullName(false, true),
            $author->getLocalizedData('affiliation'),
            DatasetAuthor::IDENTIFIER_SCHEME_ORCID,
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

    public function getDatasetRelatedPublication($publication): DatasetRelatedPublication
    {
        import('plugins.generic.dataverse.classes.APACitation');
        $apaCitation = new APACitation();
        $submissionCitation = $apaCitation->getFormattedCitationBySubmission($this->submission);
        $submissionDoi = $publication->getStoredPubId('doi');

        if (empty($submissionDoi)) {
            return new DatasetRelatedPublication(
                $this->submission->getData('datasetRelationType') ?? DataverseMetadata::DEFAULT_RELATION_TYPE,
                $submissionCitation,
                null,
                null,
                null
            );
        }

        return new DatasetRelatedPublication(
            $this->submission->getData('datasetRelationType') ?? DataverseMetadata::DEFAULT_RELATION_TYPE,
            $submissionCitation,
            'doi',
            $submissionDoi,
            "https://doi.org/" . $this->doiURLEncode($submissionDoi)
        );
    }

    private function doiURLEncode($doi)
    {
        $search = ['%', '"', '#', ' ', '<', '>', '{'];
        $replace = ['%25', '%22', '%23', '%20', '%3c', '%3e', '%7b'];
        return str_replace($search, $replace, $doi);
    }

    private function getDatasetFiles(): array
    {
        $draftDatasetFileDAO = DAORegistry::getDAO('DraftDatasetFileDAO');
        $draftDatasetFiles = $draftDatasetFileDAO->getBySubmissionId($this->submission->getId());

        if (empty($draftDatasetFiles)) {
            return [];
        }

        import('plugins.generic.dataverse.classes.draftDatasetFile.DraftDatasetFileStorage');
        $storage = new DraftDatasetFileStorage();
        $datasetFiles = array_map(function (DraftDatasetFile $draft) use ($storage) {
            $source = $storage->getSource($draft);
            if ($source === null) {
                throw new MissingDepositFileException(__('plugins.generic.dataverse.error.sourceFileMissing'));
            }
            $datasetFile = new DatasetFile();
            $datasetFile->setId($source->getId());
            $datasetFile->setOriginalFileName($source->getOriginalFileName());
            $datasetFile->setPath($source->getFilePath());
            return $datasetFile;
        }, $draftDatasetFiles);

        return $datasetFiles;
    }
}
