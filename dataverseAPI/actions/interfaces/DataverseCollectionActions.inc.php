<?php

interface DataverseCollectionActionsInterface
{
    public function get(): DataverseCollection;

    public function getRoot(): DataverseCollection;

    public function publish(): void;
}
