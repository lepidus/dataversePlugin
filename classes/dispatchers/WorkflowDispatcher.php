<?php

namespace APP\plugins\generic\dataverse\classes\dispatchers;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\plugins\Hook;

class WorkflowDispatcher extends DataverseDispatcher
{
    protected function registerHooks(): void
    {
        Hook::add('TemplateManager::setupBackendPage', [$this, 'addWorkflowAssets']);
    }

    public function addWorkflowAssets(string $hookName): bool
    {
        $request = Application::get()->getRequest();

        if ($request->getRequestedPage() !== 'dashboard') {
            return Hook::CONTINUE;
        }

        $this->addPluginAssets(TemplateManager::getManager($request));

        return Hook::CONTINUE;
    }
}
