<?php

namespace APP\plugins\generic\dataverse\report\services;

use APP\core\Application;
use APP\decision\Decision;
use APP\submission\Submission;
use PKP\db\DAORegistry;
use APP\plugins\generic\dataverse\report\services\queryBuilders\DataverseReportQueryBuilder;
use APP\plugins\generic\dataverse\dataverseAPI\search\DataverseSearchBuilder;

class DataverseReportService
{
    private int $contextId;

    public function __construct(int $contextId)
    {
        $this->contextId = $contextId;
    }

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
            'decisions' => [Decision::DECLINE, Decision::INITIAL_DECLINE],
            'statuses' => [Submission::STATUS_DECLINED]
        ]);
    }

    public function getPublishedSubmissionsCount(): int
    {
        return $this->countSubmissions([
            'contextIds' => [$this->contextId],
            'statuses' => [Submission::STATUS_PUBLISHED]
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
        $declinedArgs = [
            'contextIds' => [$this->contextId],
            'decisions' => [Decision::DECLINE, Decision::INITIAL_DECLINE],
            'statuses' => [Submission::STATUS_DECLINED]
        ];
        $declinedWithDatasetIds = $this->getQueryBuilder($declinedArgs)
            ->filterWithDataset()
            ->getSubmissionIds();

        $possibleDepositMessages = [
            'plugins.generic.dataverse.log.researchDataDeposited',
            'Research data deposited',
            'Datos de investigación depositados',
            'Dados de pesquisa depositados'
        ];
        $declinedWithDepositLogIds = $this->getQueryBuilder($declinedArgs)
            ->filterWithEventLogs($possibleDepositMessages)
            ->getSubmissionIds();

        $totalDeclined = array_unique(
            array_merge($declinedWithDatasetIds, $declinedWithDepositLogIds)
        );

        return count($totalDeclined);
    }

    public function getPublishedSubmissionsWithDatasetCount(): int
    {
        return $this->countSubmissionsWithDataset([
            'contextIds' => [$this->contextId],
            'statuses' => [Submission::STATUS_PUBLISHED]
        ]);
    }

    public function getTotalSubmissionsCount(): int
    {
        return $this->countSubmissions([
            'contextIds' => [$this->contextId],
        ]);
    }

    public function getDatasetsWithDepositErrorCount(): int
    {
        return $this->countSubmissionsWithEventLog(
            [
                'plugins.generic.dataverse.error.depositFailed',
                'plugins.generic.dataverse.error.datasetDeposit',
                'plugins.generic.dataverse.error.datasetFileDeposit'
            ],
            ['contextIds' => [$this->contextId]]
        );
    }

    public function getDatasetsWithPublishErrorCount(): int
    {
        return $this->countSubmissionsWithEventLog(
            ['plugins.generic.dataverse.error.publishFailed'],
            ['contextIds' => [$this->contextId]]
        );
    }

    private function countSubmissions(array $args = []): int
    {
        return $this->getQueryBuilder($args)
            ->getCount();
    }

    private function countSubmissionsWithDataset(array $args = []): int
    {
        return $this->getQueryBuilder($args)
            ->filterWithDataset()
            ->getCount();
    }

    private function countSubmissionsWithEventLog(array $messages, array $args = []): int
    {
        return $this->getQueryBuilder($args)
            ->filterWithEventLogs($messages)
            ->getCount();
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
        if (!empty($args['statuses'])) {
            $queryBuilder->filterByStatuses($args['statuses']);
        }

        return $queryBuilder;
    }

    public function countDatasetFiles(): int
    {
        $args = ['contextIds' => [$this->contextId]];
        $submissionsWithDataset = $this->getQueryBuilder()
            ->filterWithDataset()
            ->getQuery()
            ->get();

        $searchBuilder = $this->getDataverseSearchBuilder()->addType('file');

        foreach ($submissionsWithDataset as $submission) {
            $searchBuilder->addFilterQuery('parentIdentifier', $submission->persistent_id);
        }

        return $searchBuilder->count();
    }

    public function getDataverseSearchBuilder(): DataverseSearchBuilder
    {
        $configuration = DAORegistry::getDAO('DataverseConfigurationDAO')->get($this->contextId);
        $httpClient = Application::get()->getHttpClient();

        return new DataverseSearchBuilder($configuration, $httpClient);
    }
}
