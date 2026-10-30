<?php

namespace APP\plugins\generic\dataverse\report\services;

use APP\core\Application;
use APP\decision\Decision;
use APP\submission\Submission;
use PKP\db\DAORegistry;
use APP\plugins\generic\dataverse\classes\services\DataStatementService;
use APP\plugins\generic\dataverse\dataverseAPI\search\DataverseSearchBuilder;
use APP\plugins\generic\dataverse\report\classes\DataStatementStats;
use APP\plugins\generic\dataverse\report\services\queryBuilders\DataverseReportQueryBuilder;

class DataverseReportService
{
    private const DECLINE_DECISIONS = [Decision::DECLINE, Decision::INITIAL_DECLINE];
    private const DEPOSITED_MESSAGES = ['plugins.generic.dataverse.log.researchDataDeposited'];
    private const DEPOSIT_ERROR_MESSAGES = [
        'plugins.generic.dataverse.error.datasetDeposit',
        'plugins.generic.dataverse.error.datasetFileDeposit',
        'plugins.generic.dataverse.error.depositPending',
    ];
    private const PUBLISH_ERROR_MESSAGES = ['plugins.generic.dataverse.error.publishFailed'];

    private ?string $beginSubmissionInterval = null;
    private ?string $endSubmissionInterval = null;
    private ?string $beginFinalDecisionInterval = null;
    private ?string $endFinalDecisionInterval = null;

    public function __construct(
        private int $contextId,
        private string $applicationName
    ) {
    }

    public function setDateSubmittedInterval(string $beginning, string $ending): void
    {
        $this->beginSubmissionInterval = $beginning;
        $this->endSubmissionInterval = $ending;
    }

    public function setFinalDecisionDateInterval(string $beginning, string $ending): void
    {
        $this->beginFinalDecisionInterval = $beginning;
        $this->endFinalDecisionInterval = $ending;
    }

    public function getAcceptedSubmissionsCount(): int
    {
        return $this->getQueryBuilder($this->acceptedArgs())->getCount();
    }

    public function getDeclinedSubmissionsCount(): int
    {
        return $this->getQueryBuilder($this->declinedArgs())->getCount();
    }

    public function getPublishedSubmissionsCount(): int
    {
        return $this->getQueryBuilder(['statuses' => [Submission::STATUS_PUBLISHED]])->getCount();
    }

    public function getTotalSubmissionsCount(): int
    {
        return $this->getQueryBuilder()->getCount();
    }

    public function getAcceptedSubmissionsWithDatasetCount(): int
    {
        return $this->getQueryBuilder($this->acceptedArgs())->filterWithDataset()->getCount();
    }

    public function getPublishedSubmissionsWithDatasetCount(): int
    {
        return $this->getQueryBuilder(['statuses' => [Submission::STATUS_PUBLISHED]])->filterWithDataset()->getCount();
    }

    public function getDeclinedSubmissionsWithDatasetCount(): int
    {
        $withDatasetIds = $this->getQueryBuilder($this->declinedArgs())
            ->filterWithDataset()
            ->getSubmissionIds();
        $withDepositLogIds = $this->getQueryBuilder($this->declinedArgs())
            ->filterWithEventLogs($this->getLoggedMessages(self::DEPOSITED_MESSAGES))
            ->getSubmissionIds();

        return count(array_unique(array_merge($withDatasetIds, $withDepositLogIds)));
    }

    public function getDatasetsWithDepositErrorCount(): int
    {
        return $this->getQueryBuilder()
            ->filterWithEventLogs($this->getLoggedMessages(self::DEPOSIT_ERROR_MESSAGES))
            ->getCount();
    }

    public function getDatasetsWithPublishErrorCount(): int
    {
        return $this->getQueryBuilder()
            ->filterWithEventLogs($this->getLoggedMessages(self::PUBLISH_ERROR_MESSAGES))
            ->getCount();
    }

    public function getAcceptedStatementStatistics(): DataStatementStats
    {
        return $this->getStatementStatistics($this->acceptedArgs());
    }

    public function getPublishedStatementStatistics(): DataStatementStats
    {
        return $this->getStatementStatistics(['statuses' => [Submission::STATUS_PUBLISHED]]);
    }

    public function getDeclinedStatementStatistics(): DataStatementStats
    {
        return $this->getStatementStatistics($this->declinedArgs());
    }

    public function getTotalStatementStatistics(): DataStatementStats
    {
        return $this->getStatementStatistics();
    }

    public function countDatasetFiles(): int
    {
        $submissionsWithDataset = $this->getQueryBuilder()->filterWithDataset()->getQuery()->get();

        if ($submissionsWithDataset->isEmpty()) {
            return 0;
        }

        $searchBuilder = $this->getDataverseSearchBuilder()->addType('file');
        foreach ($submissionsWithDataset as $submission) {
            $searchBuilder->addFilterQuery('parentIdentifier', $submission->persistent_id);
        }

        return $searchBuilder->count();
    }

    public function getQueryBuilder(array $args = []): DataverseReportQueryBuilder
    {
        $queryBuilder = (new DataverseReportQueryBuilder($this->applicationName))
            ->filterByContexts([$this->contextId]);

        if (!empty($args['decisions'])) {
            $queryBuilder->filterByDecisions($args['decisions']);
        }
        if (!empty($args['statuses'])) {
            $queryBuilder->filterByStatuses($args['statuses']);
        }
        if (!empty($args['excludedStatuses'])) {
            $queryBuilder->excludeStatuses($args['excludedStatuses']);
        }
        if (!empty($this->beginSubmissionInterval) && !empty($this->endSubmissionInterval)) {
            $queryBuilder->withinDateSubmittedInterval($this->beginSubmissionInterval, $this->endSubmissionInterval);
        }
        if (!empty($this->beginFinalDecisionInterval) && !empty($this->endFinalDecisionInterval)) {
            $queryBuilder->withinFinalDecisionDateInterval(
                $this->beginFinalDecisionInterval,
                $this->endFinalDecisionInterval
            );
        }

        return $queryBuilder;
    }

    public function getDataverseSearchBuilder(): DataverseSearchBuilder
    {
        $configuration = DAORegistry::getDAO('DataverseConfigurationDAO')->get($this->contextId);

        return new DataverseSearchBuilder($configuration, Application::get()->getHttpClient());
    }

    private function acceptedArgs(): array
    {
        return [
            'decisions' => [Decision::ACCEPT],
            'excludedStatuses' => [Submission::STATUS_DECLINED],
        ];
    }

    private function declinedArgs(): array
    {
        return [
            'decisions' => self::DECLINE_DECISIONS,
            'statuses' => [Submission::STATUS_DECLINED],
        ];
    }

    private function getStatementStatistics(array $args = []): DataStatementStats
    {
        $statementTypesCounts = [
            DataStatementService::DATA_STATEMENT_TYPE_IN_MANUSCRIPT => 0,
            DataStatementService::DATA_STATEMENT_TYPE_REPO_AVAILABLE => 0,
            DataStatementService::DATA_STATEMENT_TYPE_ON_DEMAND => 0,
            DataStatementService::DATA_STATEMENT_TYPE_PUBLICLY_UNAVAILABLE => 0
        ];

        foreach ($this->getQueryBuilder($args)->getDataStatementTypes() as $statementTypes) {
            foreach ($statementTypes as $statementType) {
                if (isset($statementTypesCounts[$statementType])) {
                    $statementTypesCounts[$statementType]++;
                }
            }
        }

        return new DataStatementStats($statementTypesCounts);
    }

    private function getLoggedMessages(array $messageKeys): array
    {
        $locales = Application::get()->getRequest()->getSite()->getInstalledLocales();
        $messages = [];

        foreach ($messageKeys as $messageKey) {
            $messages[] = $messageKey;
            foreach ($locales as $locale) {
                $messages[] = strstr(__($messageKey, [], $locale), '{$', true) ?: __($messageKey, [], $locale);
            }
        }

        return array_values(array_unique($messages));
    }
}
