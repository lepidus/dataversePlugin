<?php

namespace APP\plugins\generic\dataverse\report\services;

use APP\core\Application;
use APP\decision\Decision;
use PKP\db\DAORegistry;
use APP\plugins\generic\dataverse\report\services\queryBuilders\DataverseReportQueryBuilder;
use APP\plugins\generic\dataverse\dataverseAPI\search\DataverseSearchBuilder;

class DataverseReportService
{
    public function __construct(
        private int $contextId
    ) {}

    public function getAcceptedSubmissionsCount(): int
    {
        return $this->countSubmissions([
            'contextIds' => [$this->contextId],
            'decisions' => [Decision::ACCEPT]
        ]);
    }

    public function getDeclinedSubmissionsCount(): int
    {
        return $this->countSubmissions([
            'contextIds' => [$this->contextId],
            'decisions' => [Decision::DECLINE, Decision::INITIAL_DECLINE]
        ]);
    }

    public function getAcceptedSubmissionsWithDatasetCount(): int
    {
        return $this->countSubmissionsWithDataset([
            'contextIds' => [$this->contextId],
            'decisions' => [Decision::ACCEPT]
        ]);
    }

    public function getDeclinedSubmissionsWithDatasetCount(): int
    {
        return $this->countSubmissionsWithDataset([
            'contextIds' => [$this->contextId],
            'decisions' => [Decision::DECLINE, Decision::INITIAL_DECLINE]
        ]);
    }

    private function countSubmissions(array $args = []): int
    {
        return $this->getQueryBuilder($args)->getQuery()->count();
    }

    private function countSubmissionsWithDataset(array $args = []): int
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

        return $searchBuilder->count();
    }

    public function getDataverseSearchBuilder(int $contextId): DataverseSearchBuilder
    {
        $configuration = DAORegistry::getDAO('DataverseConfigurationDAO')->get($contextId);
        $httpClient = Application::get()->getHttpClient();

        return new DataverseSearchBuilder($configuration, $httpClient);
    }
}
