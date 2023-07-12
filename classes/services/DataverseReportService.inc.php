<?php

class DataverseReportService
{
    public function getReportHeaders(): array
    {
        return [
            __('plugins.reports.dataverse.headers.acceptedSubmissions'),
            __('plugins.reports.dataverse.headers.acceptedSubmissionsWithDataset'),
            __('plugins.reports.dataverse.headers.declinedSubmissions'),
            __('plugins.reports.dataverse.headers.declinedSubmissionsWithDataset'),
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
