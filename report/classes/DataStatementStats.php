<?php

namespace APP\plugins\generic\dataverse\report\classes;

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
            $this->stats['inManuscriptCount'],
            $this->stats['repoAvailableCount'],
            $this->stats['dataverseSubmittedCount'],
            $this->stats['onDemandCount'],
            $this->stats['publiclyUnavailableCount']
        ];
    }
}
