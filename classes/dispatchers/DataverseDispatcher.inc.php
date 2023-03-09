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
}
