<?php

use APP\core\Application;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfigurationDAO;
use APP\plugins\generic\dataverse\classes\DataEncryption;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\classes\facades\Repo;
use APP\plugins\generic\dataverse\classes\factories\SubmissionDatasetFactory;
use APP\plugins\generic\dataverse\classes\services\DatasetService;
use APP\plugins\generic\dataverse\classes\services\DataStatementService;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DataverseCollectionActions;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use Illuminate\Support\Facades\Cache;
use PKP\cliTool\CommandLineTool;
use PKP\core\Core;
use PKP\core\Registry;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
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
Locale::setLocale('en');

$context = Application::getContextDAO()->getByPath(CONTEXT_PATH);
$command = $argv[1] ?? '';

$output = match ($command) {
    'configure' => configure($context),
    'create-submission' => createSubmission($context, $argv[2] ?? 'details', json_decode($argv[3] ?? '{}', true)),
    'delete-dataset' => deleteDataset((int) ($argv[2] ?? 0)),
    'event-log' => eventLog((int) ($argv[2] ?? 0)),
    'delete-dataset-by-persistent-id' => deleteDatasetByPersistentId($argv[2] ?? ''),
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

function createSubmission($context, string $scenario, array $options): array
{
    $author = Repo::user()->getByUsername(AUTHOR_USERNAME, true);
    $authorGroup = UserGroup::withContextIds([$context->getId()])
        ->withRoleIds([Role::ROLE_ID_AUTHOR])
        ->get()
        ->first(fn (UserGroup $group) => $group->nameLocaleKey === 'default.groups.name.author');
    $section = Repo::section()->getCollector()->filterByContextIds([$context->getId()])->getMany()->first();

    $isSubmitted = in_array($scenario, ['submitted', 'deposited']);
    $isDeposited = $scenario === 'deposited';
    $hasDraftDatasetFiles = $isDeposited || $scenario === 'ready-to-submit';
    $isDepositingInDataverse = $hasDraftDatasetFiles || $scenario === 'dataverse';
    $title = 'Sustainable Cities: Co-benefits of mass public transportation in climate change mitigation ' . uniqid();

    $submission = Repo::submission()->newDataObject(array_merge([
        'contextId' => $context->getId(),
        'locale' => 'en',
        'submissionProgress' => 'details',
    ], $isSubmitted ? submittedData($options['stage'] ?? null) : []));
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
    Repo::publication()->edit($submission->getCurrentPublication(), array_merge(
        ['primaryContactId' => $authorId],
        ($options['stage'] ?? null) === 'production' ? issueData($context) : [],
        $options['dataStatement'] ?? []
    ));
    Repo::stageAssignment()->build($submissionId, $authorGroup->id, $author->getId(), null, $options['authorCanEdit'] ?? true);

    if ($isDepositingInDataverse) {
        $publication = Repo::publication()->get($submission->getData('currentPublicationId'));
        Repo::publication()->edit($publication, [
            'dataStatementTypes' => array_values(array_unique(array_merge(
                $publication->getData('dataStatementTypes') ?? [],
                [DataStatementService::DATA_STATEMENT_TYPE_DATAVERSE_SUBMITTED]
            ))),
        ]);
    }

    if ($hasDraftDatasetFiles) {
        addGalley($context, $submission, $author->getId());
        addDraftDatasetFile($submission, $author->getId(), 'LEIAME.pdf', 'application/pdf', '%PDF-1.4 readme of the research data');
        addDraftDatasetFile($submission, $author->getId(), 'Planilha_de_dados_ÇÕÔÁÀÃ.json', 'application/json', '{"measurements": [1, 2, 3]}');
    }

    if ($isDeposited) {
        deposit(Repo::submission()->get($submissionId), $author);
    }

    return [
        'id' => $submissionId,
        'title' => $title,
        'persistentId' => Repo::dataverseStudy()->getBySubmissionId($submissionId)?->getPersistentId(),
    ];
}

function submittedData(?string $stage): array
{
    $isProduction = $stage === 'production' || Application::get()->getName() !== 'ojs2';

    return [
        'submissionProgress' => '',
        'dateSubmitted' => Core::getCurrentDate(),
        'stageId' => $isProduction ? WORKFLOW_STAGE_ID_PRODUCTION : WORKFLOW_STAGE_ID_SUBMISSION,
    ];
}

function issueData($context): array
{
    if (Application::get()->getName() !== 'ojs2') {
        return [];
    }

    $issue = Repo::issue()->getCollector()
        ->filterByContextIds([$context->getId()])
        ->filterByPublished(true)
        ->getMany()
        ->first();

    return ['issueId' => $issue->getId()];
}

function deposit($submission, $author): void
{
    Registry::set('user', $author);
    $submission->setData('datasetLanguage', 'French');
    $submission->setData('datasetSubject', 'Earth and Environmental Sciences');
    $submission->setData('datasetLicense', 'CC BY 4.0');
    $submission->setData('datasetRelationType', 'IsSupplementedBy');

    $dataset = (new SubmissionDatasetFactory($submission))->getDataset();
    $depositInfo = (new DatasetService())->deposit($submission, $dataset);
    if ($depositInfo['status'] !== 'Success') {
        throw new RuntimeException(__($depositInfo['message'], $depositInfo['messageParams']));
    }
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

function eventLog(int $submissionId): array
{
    return Repo::eventLog()->getCollector()
        ->filterByAssoc(Application::ASSOC_TYPE_SUBMISSION, [$submissionId])
        ->getMany()
        ->map(fn ($eventLog) => [
            'message' => $eventLog->getMessage(),
            'username' => $eventLog->getUserId() ? Repo::user()->get($eventLog->getUserId(), true)?->getUsername() : null,
        ])
        ->values()
        ->all();
}

function deleteDataset(int $submissionId): array
{
    $study = Repo::dataverseStudy()->getBySubmissionId($submissionId);
    if (!$study) {
        return ['deleted' => false];
    }

    return deleteDatasetByPersistentId($study->getPersistentId());
}

function deleteDatasetByPersistentId(string $persistentId): array
{
    try {
        (new DataverseClient())->getDatasetActions()->delete($persistentId);
    } catch (DataverseException $e) {
        return ['deleted' => false, 'persistentId' => $persistentId, 'error' => $e->getMessage()];
    }

    return ['deleted' => true, 'persistentId' => $persistentId];
}
