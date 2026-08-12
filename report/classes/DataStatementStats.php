<?php

namespace APP\plugins\generic\dataverse\report\classes;

use APP\plugins\generic\dataverse\classes\services\DataStatementService;

class DataStatementStats
{
    public function __construct(
        private array $stats
    ) {}

    public function getStats(): array
    {
        if (empty($this->stats)) {
            return [];
        }

        return [
            $this->stats[DataStatementService::DATA_STATEMENT_TYPE_IN_MANUSCRIPT],
            $this->stats[DataStatementService::DATA_STATEMENT_TYPE_REPO_AVAILABLE],
            $this->stats[DataStatementService::DATA_STATEMENT_TYPE_ON_DEMAND],
            $this->stats[DataStatementService::DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE]
        ];
    }
}
