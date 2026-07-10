<?php

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
            ROUTE_API,
            $context->getPath(),
            $handlerPath,
            null,
            null,
            $params
        );
    }
}
