<?php

use APP\core\Application;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfigurationDAO;
use APP\plugins\generic\dataverse\classes\DataEncryption;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\classes\services\DataStatementService;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DataverseCollectionActions;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use Illuminate\Support\Facades\Cache;
use PKP\cliTool\CommandLineTool;
use PKP\core\Core;
use PKP\db\DAORegistry;
use PKP\file\TemporaryFileManager;
use PKP\security\Role;
use PKP\submissionFile\SubmissionFile;
use PKP\userGroup\UserGroup;

const CONTEXT_PATH = 'publicknowledge';
const AUTHOR_USERNAME = 'eostrom';

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$_SERVER['PATH_INFO'] = CONTEXT_PATH . '/submission';
define('INDEX_FILE_LOCATION', getcwd() . '/index.php');
require './lib/pkp/classes/cliTool/CommandLineTool.php';
new CommandLineTool();

$context = Application::getContextDAO()->getByPath(CONTEXT_PATH);
$command = $argv[1] ?? '';

$output = match ($command) {
    'configure' => configure($context),
    'create-submission' => createSubmission($context, $argv[2] ?? 'details'),
    'delete-dataset' => deleteDataset((int) ($argv[2] ?? 0)),
    default => throw new InvalidArgumentException("Unknown command: {$command}"),
};

echo json_encode($output), "\n";

function configure($context): array
{
    $credentials = [
        'dataverseUrl' => preg_replace('/\/+$/', '', (string) getenv('DATAVERSE_URL')),
        'apiToken' => getenv('DATAVERSE_API_TOKEN'),
        'termsOfUse' => getenv('DATAVERSE_TERMS_OF_USE'),
    ];
    if (in_array(false, $credentials, true) || in_array('', $credentials, true)) {
        throw new RuntimeException('Set DATAVERSE_URL, DATAVERSE_API_TOKEN and DATAVERSE_TERMS_OF_USE');
    }

    DAORegistry::getDAO('PluginSettingsDAO')->updateSetting($context->getId(), 'dataverseplugin', 'enabled', true, 'bool');

    $configuration = new DataverseConfiguration();
    $configuration->setAllData([
        'dataverseUrl' => $credentials['dataverseUrl'],
        'apiToken' => (new DataEncryption())->encryptString($credentials['apiToken']),
        'termsOfUse' => ['en' => $credentials['termsOfUse']],
        'additionalInstructions' => [],
        'datasetPublish' => DataverseConfiguration::DATASET_PUBLISH_SUBMISSION_PUBLISHED,
    ]);
    (new DataverseConfigurationDAO())->insert($context->getId(), $configuration);

    foreach (['dataverse_collection', 'root_dataverse_collection', 'dataverse_licenses', 'dataverse_required_metadata'] as $cacheId) {
        Cache::forget(DataverseCollectionActions::getCacheKey($cacheId, $context->getId()));
    }

    return ['configured' => true];
}

function createSubmission($context, string $scenario): array
{
    $author = Repo::user()->getByUsername(AUTHOR_USERNAME, true);
    $authorGroup = UserGroup::withContextIds([$context->getId()])
        ->withRoleIds([Role::ROLE_ID_AUTHOR])
        ->get()
        ->first(fn (UserGroup $group) => $group->nameLocaleKey === 'default.groups.name.author');
    $section = Repo::section()->getCollector()->filterByContextIds([$context->getId()])->getMany()->first();

    $isReadyToSubmit = $scenario === 'ready-to-submit';
    $isDepositingInDataverse = $isReadyToSubmit || $scenario === 'dataverse';
    $title = 'Sustainable Cities: Co-benefits of mass public transportation in climate change mitigation ' . uniqid();

    $submission = Repo::submission()->newDataObject([
        'contextId' => $context->getId(),
        'locale' => 'en',
        'submissionProgress' => 'details',
    ]);
    $publication = Repo::publication()->newDataObject([
        'locale' => 'en',
        'sectionId' => $section->getId(),
        'title' => ['en' => $title],
        'abstract' => ['en' => 'Mass public transportation can be used as a way to reduce greenhouse gases emissions.'],
        'keywords' => ['en' => ['mass public transport', 'sustainable cities']],
    ]);
    $submissionId = Repo::submission()->add($submission, $publication, $context);
    $submission = Repo::submission()->get($submissionId);

    $authorId = Repo::author()->add(Repo::author()->newDataObject([
        'publicationId' => $submission->getData('currentPublicationId'),
        'userGroupId' => $authorGroup->id,
        'email' => $author->getEmail(),
        'givenName' => $author->getGivenName(null),
        'familyName' => $author->getFamilyName(null),
        'country' => $author->getCountry(),
        'includeInBrowse' => true,
    ]));
    Repo::publication()->edit($submission->getCurrentPublication(), ['primaryContactId' => $authorId]);
    Repo::stageAssignment()->build($submissionId, $authorGroup->id, $author->getId(), null, true);

    if ($isDepositingInDataverse) {
        Repo::publication()->edit(Repo::publication()->get($submission->getData('currentPublicationId')), [
            'dataStatementTypes' => [DataStatementService::DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED],
        ]);
    }

    if ($isReadyToSubmit) {
        addGalley($context, $submission, $author->getId());
        addDraftDatasetFile($submission, $author->getId(), 'LEIAME.pdf', 'application/pdf', '%PDF-1.4 readme of the research data');
        addDraftDatasetFile($submission, $author->getId(), 'Planilha_de_dados.json', 'application/json', '{"measurements": [1, 2, 3]}');
    }

    return ['id' => $submissionId, 'title' => $title];
}

function addGalley($context, $submission, int $userId): void
{
    $localPath = tempnam(sys_get_temp_dir(), 'dataverse');
    file_put_contents($localPath, '%PDF-1.4 manuscript of the submission');
    $submissionDir = Repo::submissionFile()->getSubmissionDir($context->getId(), $submission->getId());
    $fileId = app()->get('file')->add($localPath, $submissionDir . '/' . uniqid() . '.pdf');
    unlink($localPath);

    Repo::submissionFile()->add(Repo::submissionFile()->newDataObject([
        'fileId' => $fileId,
        'fileStage' => SubmissionFile::SUBMISSION_FILE_SUBMISSION,
        'genreId' => DAORegistry::getDAO('GenreDAO')->getByKey('SUBMISSION', $context->getId())->getId(),
        'name' => ['en' => 'manuscript.pdf'],
        'submissionId' => $submission->getId(),
        'uploaderUserId' => $userId,
    ]));
}

function addDraftDatasetFile($submission, int $userId, string $fileName, string $mimeType, string $contents): void
{
    $temporaryFileManager = new TemporaryFileManager();
    $serverFileName = uniqid('dataverse') . '.' . pathinfo($fileName, PATHINFO_EXTENSION);
    $temporaryFileManager->writeFile($temporaryFileManager->getBasePath() . $serverFileName, $contents);

    $temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO');
    $temporaryFile = $temporaryFileDao->newDataObject();
    $temporaryFile->setUserId($userId);
    $temporaryFile->setServerFileName($serverFileName);
    $temporaryFile->setFileType($mimeType);
    $temporaryFile->setFileSize(strlen($contents));
    $temporaryFile->setOriginalFileName($fileName);
    $temporaryFile->setDateUploaded(Core::getCurrentDate());

    $draftDatasetFile = Repo::draftDatasetFile()->newDataObject();
    $draftDatasetFile->setAllData([
        'submissionId' => $submission->getId(),
        'userId' => $userId,
        'fileId' => $temporaryFileDao->insertObject($temporaryFile),
        'fileName' => $fileName,
    ]);
    Repo::draftDatasetFile()->add($draftDatasetFile);
}

function deleteDataset(int $submissionId): array
{
    $study = Repo::dataverseStudy()->getBySubmissionId($submissionId);
    if (!$study) {
        return ['deleted' => false];
    }

    (new DataverseClient())->getDatasetActions()->delete($study->getPersistentId());

    return ['deleted' => true, 'persistentId' => $study->getPersistentId()];
}
