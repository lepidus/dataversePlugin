<?php

class DataverseReportService
{
    public function getReportHeaders(): array
    {
        return [
            __('plugins.generic.dataverse.report.headers.acceptedSubmissions'),
            __('plugins.generic.dataverse.report.headers.acceptedSubmissionsWithDataset'),
            __('plugins.generic.dataverse.report.headers.declinedSubmissions'),
            __('plugins.generic.dataverse.report.headers.declinedSubmissionsWithDataset'),
        ];
    }

    public function countSubmissions(array $args = []): int
    {
        return $this->getQueryBuilder($args)->getQuery()->count();
    }

    public function countSubmissionsWithDataset(array $args = []): int
    {
        return $this->getQueryBuilder($args)->getWithDataset()->count();
    }

    public function getQueryBuilder($args = []): DataverseReportQueryBuilder
    {
        $qb = new DataverseReportQueryBuilder();

        if (!empty($args['contextIds'])) {
            $qb->filterByContexts($args['contextIds']);
        }
        if (!empty($args['decisions'])) {
            $qb->filterByDecisions($args['decisions']);
        }

        return $qb;
    }
}
