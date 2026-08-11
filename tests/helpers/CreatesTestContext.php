<?php

namespace APP\plugins\generic\dataverse\tests\helpers;

use APP\core\Application;
use PKP\context\Context;

/**
 * Creates a context (journal/server) to be used by tests that need to persist
 * submissions or plugin settings. Since OJS/OPS 3.5 those tables have foreign
 * keys to the context table, so tests can no longer rely on arbitrary ids.
 */
trait CreatesTestContext
{
    protected function createTestContext(): Context
    {
        $contextDAO = Application::getContextDAO();

        $context = $contextDAO->newDataObject();
        $context->setPath('dataverse-test-' . uniqid());
        $context->setPrimaryLocale('en');
        $context->setData('supportedSubmissionLocales', ['en']);
        $context->setData('supportedDefaultSubmissionLocale', 'en');
        $context->setId($contextDAO->insertObject($context));

        return $context;
    }

    protected function deleteTestContext(Context $context): void
    {
        Application::getContextDAO()->deleteObject($context);
    }
}
