<?php

namespace APP\plugins\generic\dataverse\classes\dispatchers;

use PKP\plugins\Plugin;

abstract class DataverseDispatcher
{
    protected $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
        $this->registerHooks();
    }

    abstract protected function registerHooks(): void;
}
