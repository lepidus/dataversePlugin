<?php

/** Reset only Dataverse's test configuration; never exposed as a web endpoint. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__, 5) . '/tools/bootstrap.php';

use APP\core\Application;
use PKP\cache\CacheManager;
use PKP\cliTool\CommandLineTool;
use PKP\config\Config;
use PKP\db\DAORegistry;

class ResetDataverseTestConfiguration extends CommandLineTool
{
    public function execute()
    {
        $expectedDatabase = $this->argv[0] ?? '';
        if (!$expectedDatabase || Config::getVar('database', 'name') !== $expectedDatabase) {
            throw new RuntimeException('Refusing to reset an unconfirmed test database.');
        }
        $context = Application::getContextDAO()->getByPath('publicknowledge');
        if (!$context) {
            throw new RuntimeException('The publicknowledge test context is missing.');
        }
        $mode = $this->argv[1] ?? 'configuration';
        if (!in_array($mode, ['configuration', 'cache'], true)) {
            throw new RuntimeException('Unknown test reset mode.');
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
