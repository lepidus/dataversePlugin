<?php

import('plugins.generic.dataverse.report.services.queryBuilders.DataverseReportQueryBuilder');
import('plugins.generic.dataverse.dataverseAPI.search.DataverseSearchBuilder');

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
            ]),
            'datasetsWithDepositError' => $this->countDatasetsWithError(
                ['plugins.generic.dataverse.error.depositFailed'],
                ['contextIds' => [$contextId],]
            ),
            'datasetsWithPublishError' => $this->countDatasetsWithError(
                ['plugins.generic.dataverse.error.publishFailed'],
                ['contextIds' => [$contextId],]
            ),
            'filesInDatasets' => $this->countDatasetFiles($contextId),
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
            __('plugins.generic.dataverse.report.headers.datasetsWithDepositError'),
            __('plugins.generic.dataverse.report.headers.datasetsWithPublishError'),
            __('plugins.generic.dataverse.report.headers.filesInDatasets'),
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

    public function countDatasetsWithError(array $messages, array $args = []): int
    {
        return $this->getQueryBuilder($args)->countDatasetsWithError($messages);
    }

    public function getQueryBuilder($args = []): DataverseReportQueryBuilder
    {
        $queryBuilder = new DataverseReportQueryBuilder();

        if (!empty($args['contextIds'])) {
            $queryBuilder->filterByContexts($args['contextIds']);
        }
        if (!empty($args['decisions'])) {
            $queryBuilder->filterByDecisions($args['decisions']);
        }

        return $queryBuilder;
    }

    public function countDatasetFiles(int $contextId): int
    {
        $submissionsWithDataset = $this->getQueryBuilder([
            'contextIds' => [$contextId]
        ])->getWithDataset()->get();

        $searchBuilder = $this->getDataverseSearchBuilder($contextId)->addType('file');

        foreach ($submissionsWithDataset as $submission) {
            $searchBuilder->addFilterQuery('parentIdentifier', $submission->persistent_id);
        }

        $response = $searchBuilder->search();
        $data = json_decode($response->getBody(), true);
        return $data['data']['total_count'];
    }

    public function getDataverseSearchBuilder(int $contextId): DataverseSearchBuilder
    {
        $configuration = DAORegistry::getDAO('DataverseConfigurationDAO')->get($contextId);
        $httpClient = Application::get()->getHttpClient();

        return new DataverseSearchBuilder($configuration, $httpClient);
    }
}
