<?php

import('plugins.generic.dataverse.classes.services.DataStatementService');

class DataStatementStats
{
    private $stats;

    public function __construct(array $stats)
    {
        $this->stats = $stats;
    }

    public function getStats(): array
    {
        if (empty($this->stats)) {
            return [];
        }

        return [
            $this->stats[DATA_STATEMENT_TYPE_IN_MANUSCRIPT],
            $this->stats[DATA_STATEMENT_TYPE_REPO_AVAILABLE],
            $this->stats[DATA_STATEMENT_TYPE_ON_DEMAND],
            $this->stats[DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE]
        ];
    }
}
