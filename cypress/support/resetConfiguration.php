<?php

/** Reset only Dataverse's test configuration; never exposed as a web endpoint. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__, 5) . '/tools/bootstrap.php';

use APP\core\Application;
use Illuminate\Support\Facades\DB;
use PKP\cache\CacheManager;
use PKP\cliTool\CommandLineTool;
use PKP\config\Config;
use PKP\db\DAORegistry;

class ResetDataverseTestConfiguration extends CommandLineTool
{
    public function execute()
    {
        $mode = $this->argv[0] ?? 'configuration';
        if (!in_array($mode, ['configuration', 'cache', 'study', 'genres'], true)) {
            throw new RuntimeException('Unknown test reset mode.');
        }
        $args = array_slice($this->argv, 1);
        $expectedDatabase = null;
        $lastArgument = end($args);
        if (is_string($lastArgument) && str_starts_with($lastArgument, '--database=')) {
            $expectedDatabase = substr(array_pop($args), strlen('--database='));
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $expectedDatabase)) {
                throw new RuntimeException('Invalid expected test database name.');
            }
        }
        if (count($args) !== ($mode === 'study' ? 1 : 0)) {
            throw new RuntimeException('Invalid test fixture arguments.');
        }
        $database = Config::getVar('database', 'name');
        if (!$database || ($expectedDatabase !== null && $database !== $expectedDatabase)) {
            throw new RuntimeException('Refusing to reset an unconfirmed test database.');
        }
        $context = Application::getContextDAO()->getByPath('publicknowledge');
        if (!$context) {
            throw new RuntimeException('The publicknowledge test context is missing.');
        }
        if ($mode === 'genres') {
            $genres = DAORegistry::getDAO('GenreDAO')->getByContextId($context->getId())->toArray();
            echo json_encode(array_values(array_map(static fn ($genre) => [
                'id' => (int) $genre->getId(),
                'name' => $genre->getName('en'),
            ], $genres)));
            return;
        }
        if ($mode === 'study') {
            $submissionId = filter_var($args[0] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$submissionId || !DB::table('submissions')->where('submission_id', $submissionId)->where('context_id', $context->getId())->exists()) {
                throw new RuntimeException('The fixture submission does not belong to the test context.');
            }
            $studyId = DB::table('dataverse_studies')->where('submission_id', $submissionId)->value('study_id');
            echo json_encode(['studyId' => $studyId ? (int) $studyId : null]);
            return;
        }
        if ($mode === 'configuration') {
            $settingsDao = DAORegistry::getDAO('PluginSettingsDAO');
            foreach (['dataverseUrl', 'apiToken', 'termsOfUse', 'datasetPublish', 'additionalInstructions'] as $setting) {
                $settingsDao->deleteSetting($context->getId(), 'dataverseplugin', $setting);
            }
        }
        foreach (['dataverse_collection', 'root_dataverse_collection', 'dataverse_licenses', 'dataverse_required_metadata'] as $cacheId) {
            CacheManager::getManager()->getFileCache($context->getId(), $cacheId, static fn () => null)->flush();
        }
        // Explicit settings validation builds DataverseActions without a context ID.
        CacheManager::getManager()->getFileCache(null, 'dataverse_collection', static fn () => null)->flush();
    }
}

(new ResetDataverseTestConfiguration($argv))->execute();
