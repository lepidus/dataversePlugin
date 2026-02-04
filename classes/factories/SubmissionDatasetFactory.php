<?php

namespace APP\plugins\generic\dataverse\classes\factories;

use APP\submission\Submission;
use APP\author\Author;
use APP\core\Application;
use PKP\db\DAORegistry;
use PKP\file\TemporaryFile;
use PKP\file\TemporaryFileManager;
use APP\plugins\generic\dataverse\classes\factories\DatasetFactory;
use APP\plugins\generic\dataverse\classes\entities\Dataset;
use APP\plugins\generic\dataverse\classes\entities\DatasetAuthor;
use APP\plugins\generic\dataverse\classes\entities\DatasetContact;
use APP\plugins\generic\dataverse\classes\entities\DatasetFile;
use APP\plugins\generic\dataverse\classes\entities\DatasetRelatedPublication;
use APP\plugins\generic\dataverse\classes\draftDatasetFile\DraftDatasetFile;
use APP\plugins\generic\dataverse\classes\APACitation;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;

class SubmissionDatasetFactory extends DatasetFactory
{
    private $submission;
    private $draftDatasetFileRepo;

    public function __construct(Submission $submission)
    {
        $this->submission = $submission;
        $this->draftDatasetFileRepo = Repo::draftDatasetFile();
    }

    public function setDraftDatasetFileRepo($repository)
    {
        $this->draftDatasetFileRepo = $repository;
    }

    protected function sanitizeProps(): array
    {
        $publication = $this->submission->getCurrentPublication();
        $authors = $publication->getData('authors')->toArray();

        $props = [];
        $datasetTitlePrefix = __('plugins.generic.dataverse.dataset.titlePrefix');
        $props['title'] = $datasetTitlePrefix . $publication->getLocalizedData('title');
        $props['description'] = $publication->getLocalizedData('abstract');
        $props['keywords'] = $publication->getLocalizedData('keywords');
        $props['subject'] = $this->submission->getData('datasetSubject');
        $props['license'] = $this->submission->getData('datasetLicense');
        $props['authors'] = array_map([$this, 'createDatasetAuthor'], $authors);
        $props['contact'] = $this->createDatasetContact();
        $props['depositor'] = $this->getDatasetDepositor();
        $props['relatedPublication'] = $this->getDatasetRelatedPublication($publication);
        $props['files'] = $this->getDatasetFiles();

        $this->sanitizeAdditionalProps($props);

        return $props;
    }

    private function sanitizeAdditionalProps(array &$props): void
    {
        try {
            $dataverseClient = new DataverseClient();
            $dataverseCollectionActions = $dataverseClient->getDataverseCollectionActions();
            $requiredMetadata = $dataverseCollectionActions->getRequiredMetadata();
            $flattenedFields = $dataverseCollectionActions->getFlattenedFields($requiredMetadata);

            foreach ($flattenedFields as $field) {
                $fieldName = 'dataset' . ucfirst($field['name']);
                $props[$field['name']] = $this->submission->getData($fieldName) ?? null;
            }
        } catch (DataverseException $e) {
            error_log('Error getting required metadata fields: ' . $e->getMessage());
        }
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
        return preg_match("~\d{4}-\d{4}-\d{4}-\d{3}(\d|X|x)~", $orcid, $matches) ? $matches[0] : null;
    }

    private function createDatasetContact(): DatasetContact
    {
        $publication = $this->submission->getCurrentPublication();
        $primaryAuthor = $publication->getPrimaryAuthor();
        $contact = $primaryAuthor;

        if (empty($primaryAuthor)) {
            $submissionUser = Application::get()->getRequest()->getUser();
            $contact = $submissionUser;
        }

        $name = $contact->getFullName(false, true);
        $email = $contact->getEmail();
        $affiliation = $contact->getLocalizedData('affiliation');

        return new DatasetContact($name, $email, $affiliation);
    }

    private function getDatasetDepositor(): string
    {
        $submissionUser = Application::get()->getRequest()->getUser();
        $userName = $submissionUser->getFullName(false, true);

        $context = Application::getContextDAO()->getById($this->submission->getContextId());
        $contextName = $context->getLocalizedName();

        return $userName . ' (via ' . $contextName . ')';
    }

    public function getDatasetRelatedPublication($publication): DatasetRelatedPublication
    {
        $apaCitation = new APACitation();
        $submissionCitation = $apaCitation->getFormattedCitationBySubmission($this->submission, $publication);
        $doiObject = $publication->getData('doiObject');

        if (empty($doiObject)) {
            return new DatasetRelatedPublication($submissionCitation, null, null, null);
        }

        return new DatasetRelatedPublication(
            $submissionCitation,
            'doi',
            $doiObject->getDoi(),
            $doiObject->getResolvingUrl()
        );
    }

    private function getDatasetFiles(): array
    {
        $draftDatasetFileRepo = $this->draftDatasetFileRepo;
        $draftDatasetFiles = $draftDatasetFileRepo->getBySubmissionId($this->submission->getId())->toArray();

        if (empty($draftDatasetFiles)) {
            return [];
        }

        $temporaryFiles = array_map(
            function (DraftDatasetFile $draftDatasetFile) use ($draftDatasetFileRepo) {
                $temporaryFileManager = new TemporaryFileManager();
                $file = $temporaryFileManager->getFile(
                    $draftDatasetFile->getData('fileId'),
                    $draftDatasetFile->getData('userId')
                );
                if (!is_null($file)) {
                    return $file;
                }
                $draftDatasetFileRepo->delete($draftDatasetFile);
            },
            $draftDatasetFiles
        );

        if (empty(array_filter($temporaryFiles))) {
            return [];
        }

        $datasetFiles = array_map(
            function (TemporaryFile $temporaryFile) {
                $datasetFile = new DatasetFile();
                $datasetFile->setOriginalFileName($temporaryFile->getOriginalFileName());
                $datasetFile->setPath($temporaryFile->getFilePath());
                return $datasetFile;
            },
            $temporaryFiles
        );

        return $datasetFiles;
    }
}
