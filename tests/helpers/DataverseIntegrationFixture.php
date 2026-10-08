<?php

namespace APP\plugins\generic\dataverse\tests\helpers;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfigurationDAO;
use APP\plugins\generic\dataverse\classes\draftDatasetFile\DraftDatasetFile;
use APP\plugins\generic\dataverse\classes\facades\Repo as DataverseRepo;
use APP\plugins\generic\dataverse\DataversePlugin;
use APP\plugins\generic\dataverse\DataverseSettingsForm;
use APP\submission\Submission;
use PKP\context\Context;
use PKP\core\Core;
use PKP\core\Registry;
use PKP\plugins\Hook;
use PKP\db\DAORegistry;
use PKP\file\TemporaryFileManager;
use PKP\observers\events\DecisionAdded;
use PKP\observers\events\SubmissionSubmitted;
use PKP\submissionFile\SubmissionFile;
use PKP\security\Role;
use PKP\user\User;
use PKP\userGroup\UserGroup;
use Illuminate\Support\Facades\Event;

trait DataverseIntegrationFixture
{
    use CreatesTestContext;

    protected Context $context;
    protected User $user;
    protected int $sectionId;
    protected DataversePlugin $plugin;
    private array $submissionIds = [];
    private array $temporaryFileIds = [];
    private array $eventListenersBeforePlugin = [];

    protected function getMockedRegistryKeys(): array
    {
        return ['request', 'hooks', 'user'];
    }

    private function pluginEvents(): array
    {
        return [SubmissionSubmitted::class, DecisionAdded::class];
    }

    protected function setUpFixture(): void
    {
        foreach ($this->pluginEvents() as $event) {
            $this->eventListenersBeforePlugin[$event] = Event::getRawListeners()[$event] ?? [];
        }
        $this->context = $this->createTestContext();
        $this->user = $this->createUser();
        $this->sectionId = $this->createSection();
        $this->plugin = new DataversePlugin();
    }

    protected function tearDownFixture(): void
    {
        foreach ($this->eventListenersBeforePlugin as $event => $listeners) {
            Event::forget($event);
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }

        $temporaryFileManager = new TemporaryFileManager();
        foreach ($this->temporaryFileIds as $temporaryFileId) {
            $temporaryFileManager->deleteById($temporaryFileId, $this->user->getId());
        }

        foreach ($this->submissionIds as $submissionId) {
            $submission = Repo::submission()->get($submissionId);
            if ($submission) {
                Repo::submission()->delete($submission);
            }
        }

        DAORegistry::getDAO('PluginSettingsDAO')->deleteByContextId($this->context->getId());
        Repo::user()->delete($this->user);
        $this->deleteTestContext($this->context);
    }

    protected function createdSubmissionIds(): array
    {
        return $this->submissionIds;
    }

    protected function reloadSchemas(array $schemaNames = ['publication', 'submission', 'draftDatasetFile']): void
    {
        foreach ($schemaNames as $schemaName) {
            app()->get('schema')->get($schemaName, true);
        }
    }

    protected function restoreCoreSchemas(): void
    {
        $this->reloadSchemas(['publication', 'submission']);
    }

    private function createSection(): int
    {
        $section = Repo::section()->newDataObject([
            'contextId' => $this->context->getId(),
            'title' => ['en' => 'Articles'],
            'abbrev' => ['en' => 'ART'],
            'abstractsNotRequired' => false,
            'sequence' => 1,
        ]);

        return Repo::section()->add($section);
    }

    private function createUser(): User
    {
        $username = 'dataverse' . uniqid();
        $user = Repo::user()->newDataObject([
            'userName' => $username,
            'email' => $username . '@example.org',
            'password' => 'not-used',
            'givenName' => ['en' => 'Dataverse'],
            'familyName' => ['en' => 'Tester'],
            'dateRegistered' => Core::getCurrentDate(),
        ]);
        $user->setId(Repo::user()->add($user));

        return $user;
    }

    protected function saveConfiguration(array $settings): void
    {
        $configuration = new DataverseConfiguration();
        $configuration->setAllData($settings);
        (new DataverseConfigurationDAO())->insert($this->context->getId(), $configuration);
    }

    protected function configureWithPlaceholderDataverse(): void
    {
        $this->saveConfiguration([
            'dataverseUrl' => 'https://dataverse.invalid/dataverse/placeholder',
            'apiToken' => 'placeholder-token',
            'termsOfUse' => ['en' => 'https://dataverse.invalid/terms'],
        ]);
    }

    protected function dataverseCredentials(): array
    {
        $credentials = [
            'dataverseUrl' => preg_replace('/\/+$/', '', (string) getenv('DATAVERSE_URL')),
            'apiToken' => getenv('DATAVERSE_API_TOKEN'),
            'termsOfUse' => getenv('DATAVERSE_TERMS_OF_USE'),
        ];

        if (in_array(false, $credentials, true) || in_array('', $credentials, true)) {
            $message = 'Requires a real Dataverse: set DATAVERSE_URL, DATAVERSE_API_TOKEN and DATAVERSE_TERMS_OF_USE';
            getenv('CI') ? $this->fail($message) : $this->markTestSkipped($message);
        }

        return $credentials;
    }

    protected function configureWithRealDataverse(): void
    {
        $credentials = $this->dataverseCredentials();

        $_POST = [
            'dataverseUrl' => $credentials['dataverseUrl'],
            'apiToken' => $credentials['apiToken'],
            'termsOfUse' => ['en' => $credentials['termsOfUse']],
            'additionalInstructions' => ['en' => ''],
            'datasetPublish' => DataverseConfiguration::DATASET_PUBLISH_SUBMISSION_PUBLISHED,
        ];
        $hooksBeforeSettingsRequest = Hook::getHooks();
        $this->mockRequest($this->context->getPath() . '/management/settings', $this->user->getId());
        $this->plugin->register('generic', 'plugins/generic/dataverse', $this->context->getId());

        $form = new DataverseSettingsForm($this->plugin, $this->context->getId());
        $form->readInputData();
        $form->execute();

        $_POST = [];
        Registry::set('hooks', $hooksBeforeSettingsRequest);
    }

    protected function registerPlugin(string $page = 'submission'): void
    {
        $this->mockRequest($this->context->getPath() . '/' . $page, $this->user->getId());
        $this->plugin->register('generic', 'plugins/generic/dataverse', $this->context->getId());
        $this->reloadSchemas();
    }

    protected function createSubmission(array $publicationData = [], array $submissionData = []): Submission
    {
        $submission = Repo::submission()->newDataObject(array_merge([
            'contextId' => $this->context->getId(),
            'locale' => 'en',
            'submissionProgress' => 'review',
        ], $submissionData));
        $publication = Repo::publication()->newDataObject(array_merge([
            'locale' => 'en',
            'sectionId' => $this->sectionId,
            'title' => ['en' => 'Sustainable Cities: Co-benefits of mass public transportation'],
            'abstract' => ['en' => 'Mass public transportation can be used as a way to reduce greenhouse gases emissions.'],
        ], $publicationData));

        $submissionId = Repo::submission()->add($submission, $publication, $this->context);
        $this->submissionIds[] = $submissionId;

        return Repo::submission()->get($submissionId);
    }

    protected function createTemporaryFile(string $fileName, string $mimeType, string $contents): int
    {
        $temporaryFileManager = new TemporaryFileManager();
        $serverFileName = uniqid('dataverse') . '.' . pathinfo($fileName, PATHINFO_EXTENSION);
        $temporaryFileManager->writeFile($temporaryFileManager->getBasePath() . $serverFileName, $contents);

        $temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO');
        $temporaryFile = $temporaryFileDao->newDataObject();
        $temporaryFile->setUserId($this->user->getId());
        $temporaryFile->setServerFileName($serverFileName);
        $temporaryFile->setFileType($mimeType);
        $temporaryFile->setFileSize(strlen($contents));
        $temporaryFile->setOriginalFileName($fileName);
        $temporaryFile->setDateUploaded(Core::getCurrentDate());

        $temporaryFileId = $temporaryFileDao->insertObject($temporaryFile);
        $this->temporaryFileIds[] = $temporaryFileId;

        return $temporaryFileId;
    }

    protected function addDraftDatasetFile(Submission $submission, string $fileName, string $mimeType, string $contents): DraftDatasetFile
    {
        $temporaryFileId = $this->createTemporaryFile($fileName, $mimeType, $contents);

        $draftDatasetFile = DataverseRepo::draftDatasetFile()->newDataObject();
        $draftDatasetFile->setAllData([
            'submissionId' => $submission->getId(),
            'userId' => $this->user->getId(),
            'fileId' => $temporaryFileId,
            'fileName' => $fileName,
        ]);
        $draftDatasetFile->setId(DataverseRepo::draftDatasetFile()->add($draftDatasetFile));

        return $draftDatasetFile;
    }

    protected function addSubmissionFile(Submission $submission, string $fileName, string $contents): SubmissionFile
    {
        $localPath = tempnam(sys_get_temp_dir(), 'dataverse');
        file_put_contents($localPath, $contents);
        $submissionDir = Repo::submissionFile()->getSubmissionDir($this->context->getId(), $submission->getId());
        $fileId = app()->get('file')->add($localPath, $submissionDir . '/' . uniqid() . '.' . pathinfo($fileName, PATHINFO_EXTENSION));
        unlink($localPath);

        $submissionFile = Repo::submissionFile()->newDataObject([
            'fileId' => $fileId,
            'fileStage' => SubmissionFile::SUBMISSION_FILE_SUBMISSION,
            'name' => ['en' => $fileName],
            'submissionId' => $submission->getId(),
            'uploaderUserId' => $this->user->getId(),
        ]);

        return Repo::submissionFile()->get(Repo::submissionFile()->add($submissionFile));
    }

    protected function addAuthor(Submission $submission): void
    {
        $authorGroup = UserGroup::create([
            'contextId' => $this->context->getId(),
            'roleId' => Role::ROLE_ID_AUTHOR,
            'isDefault' => true,
            'showTitle' => true,
            'permitSelfRegistration' => true,
            'permitMetadataEdit' => true,
            'masthead' => false,
        ]);

        $author = Repo::author()->newDataObject([
            'publicationId' => $submission->getData('currentPublicationId'),
            'userGroupId' => $authorGroup->id,
            'email' => 'eostrom@mailinator.com',
            'givenName' => ['en' => 'Elinor'],
            'familyName' => ['en' => 'Ostrom'],
            'includeInBrowse' => true,
        ]);
        $authorId = Repo::author()->add($author);

        Repo::publication()->edit($submission->getCurrentPublication(), ['primaryContactId' => $authorId]);
    }
}
