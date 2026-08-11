<?php

namespace APP\plugins\generic\dataverse\tests\helpers;

use APP\core\Application;
use PKP\context\Context;

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
