<?php

import('plugins.generic.dataverse.report.services.queryBuilders.DataverseReportQueryBuilder');
import('plugins.generic.dataverse.dataverseAPI.search.DataverseSearchBuilder');
import('plugins.generic.dataverse.report.classes.DataStatementStats');

class DataverseReportService
{
    private $contextId;
    private $applicationName;
    private $beginSubmissionInterval;
    private $endSubmissionInterval;
    private $beginFinalDecisionInterval;
    private $endFinalDecisionInterval;

    public function __construct(int $contextId, string $applicationName)
    {
        $this->contextId = $contextId;
        $this->applicationName = $applicationName;
    }

    public function setDateSubmittedInterval(string $beginning, string $ending)
    {
        $this->beginSubmissionInterval = $beginning;
        $this->endSubmissionInterval = $ending;
    }

    public function setFinalDecisionDateInterval(string $beginning, string $ending)
    {
        $this->beginFinalDecisionInterval = $beginning;
        $this->endFinalDecisionInterval = $ending;
    }

    public function getAcceptedSubmissionsCount(): int
    {
        return $this->countSubmissions([
            'contextIds' => [$this->contextId],
            'decisions' => [SUBMISSION_EDITOR_DECISION_ACCEPT]
        ]);
    }

    public function getDeclinedSubmissionsCount(): int
    {
        return $this->countSubmissions([
            'contextIds' => [$this->contextId],
            'decisions' => [SUBMISSION_EDITOR_DECISION_DECLINE, SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE],
            'statuses' => [STATUS_DECLINED]
        ]);
    }

    public function getPublishedSubmissionsCount(): int
    {
        return $this->countSubmissions([
            'contextIds' => [$this->contextId],
            'statuses' => [STATUS_PUBLISHED]
        ]);
    }

    public function getAcceptedSubmissionsWithDatasetCount(): int
    {
        return $this->countSubmissionsWithDataset([
            'contextIds' => [$this->contextId],
            'decisions' => [SUBMISSION_EDITOR_DECISION_ACCEPT]
        ]);
    }

    public function getDeclinedSubmissionsWithDatasetCount(): int
    {
        $declinedArgs = [
            'contextIds' => [$this->contextId],
            'decisions' => [SUBMISSION_EDITOR_DECISION_DECLINE, SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE],
            'statuses' => [STATUS_DECLINED]
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
            'statuses' => [STATUS_PUBLISHED]
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

    public function getAcceptedStatementStatistics()
    {
        return $this->getStatementStatistics([
            'contextIds' => [$this->contextId],
            'decisions' => [SUBMISSION_EDITOR_DECISION_ACCEPT]
        ]);
    }

    public function getDeclinedStatementStatistics()
    {
        return $this->getStatementStatistics([
            'contextIds' => [$this->contextId],
            'decisions' => [SUBMISSION_EDITOR_DECISION_DECLINE, SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE],
            'statuses' => [STATUS_DECLINED]
        ]);
    }

    public function getPublishedStatementStatistics()
    {
        return $this->getStatementStatistics([
            'contextIds' => [$this->contextId],
            'statuses' => [STATUS_PUBLISHED]
        ]);
    }

    public function getTotalStatementStatistics()
    {
        return $this->getStatementStatistics([
            'contextIds' => [$this->contextId]
        ]);
    }

    private function getStatementStatistics(array $args): DataStatementStats
    {
        $submissionsStatementTypes = $this->getQueryBuilder($args)
            ->getDataStatementTypes();

        $statementTypesCounts = [
            DATA_STATEMENT_TYPE_IN_MANUSCRIPT => 0,
            DATA_STATEMENT_TYPE_REPO_AVAILABLE => 0,
            DATA_STATEMENT_TYPE_ON_DEMAND => 0,
            DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE => 0
        ];

        foreach ($submissionsStatementTypes as $statementTypes) {
            foreach ($statementTypes as $statementType) {
                if (isset($statementTypesCounts[$statementType])) {
                    $statementTypesCounts[$statementType]++;
                }
            }
        }

        return new DataStatementStats($statementTypesCounts);
    }

    public function getQueryBuilder($args = []): DataverseReportQueryBuilder
    {
        $queryBuilder = new DataverseReportQueryBuilder($this->applicationName);

        if (!empty($args['contextIds'])) {
            $queryBuilder->filterByContexts($args['contextIds']);
        }
        if (!empty($args['decisions'])) {
            $queryBuilder->filterByDecisions($args['decisions']);
        }
        if (!empty($args['statuses'])) {
            $queryBuilder->filterByStatuses($args['statuses']);
        }

        if (!empty($this->beginSubmissionInterval) && !empty($this->endSubmissionInterval)) {
            $queryBuilder->withinDateSubmittedInterval(
                $this->beginSubmissionInterval,
                $this->endSubmissionInterval
            );
        }

        if (!empty($this->beginFinalDecisionInterval) && !empty($this->endFinalDecisionInterval)) {
            $queryBuilder->withinFinalDecisionDateInterval(
                $this->beginFinalDecisionInterval,
                $this->endFinalDecisionInterval
            );
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
