<?php

class DataverseReportService
{
    public function getOverview(int $contextId): array
    {
        $overview = [];

        if (Application::get()->getName() === 'ojs2') {
            $overview = array_merge($overview, [
                'acceptedSubmissions' => $this->countSubmissions([
                    'contextIds' => [$contextId],
                    'decisions' => [SUBMISSION_EDITOR_DECISION_ACCEPT]
                ]),
                'acceptedSubmissionsWithDataset' => $this->countSubmissionsWithDataset([
                    'contextIds' => [$contextId],
                    'decisions' => [SUBMISSION_EDITOR_DECISION_ACCEPT]
                ])
            ]);
        }

        return array_merge($overview, [
            'declinedSubmissions' => $this->countSubmissions([
                'contextIds' => [$contextId],
                'decisions' => [SUBMISSION_EDITOR_DECISION_DECLINE, SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE]
            ]),
            'declinedSubmissionsWithDataset' => $this->countSubmissionsWithDataset([
                'contextIds' => [$contextId],
                'decisions' => [SUBMISSION_EDITOR_DECISION_DECLINE, SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE]
            ])
        ]);
    }

    public function getReportHeaders(): array
    {
        $headers = [];

        if (Application::get()->getName() === 'ojs2') {
            $headers = array_merge($headers, [
                __('plugins.generic.dataverse.report.headers.acceptedSubmissions'),
                __('plugins.generic.dataverse.report.headers.acceptedSubmissionsWithDataset'),
            ]);
        }

        return array_merge($headers, [
            __('plugins.generic.dataverse.report.headers.declinedSubmissions'),
            __('plugins.generic.dataverse.report.headers.declinedSubmissionsWithDataset'),
        ]);
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
