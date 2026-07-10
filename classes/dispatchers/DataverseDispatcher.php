<?php

namespace APP\plugins\generic\dataverse\classes\dispatchers;

use PKP\plugins\Plugin;
use APP\core\Application;

abstract class DataverseDispatcher
{
    protected $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
        $this->registerHooks();
    }

    abstract protected function registerHooks(): void;

    protected function getApiUrl(string $handlerPath, array $params = []): ?string
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        return $request->getDispatcher()->url(
            $request,
            Application::ROUTE_API,
            $context->getPath(),
            $handlerPath,
            null,
            null,
            $params
        );
    }
}
