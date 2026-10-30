<?php

namespace APP\plugins\generic\dataverse\report\services\queryBuilders;

use APP\core\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use APP\decision\Decision;
use APP\plugins\generic\dataverse\report\classes\DataverseStatsReport;

class DataverseReportQueryBuilder
{
    private const FINAL_DECISION_CHANGES = [
        Decision::ACCEPT,
        Decision::DECLINE,
        Decision::INITIAL_DECLINE,
        Decision::REVERT_DECLINE,
        Decision::REVERT_INITIAL_DECLINE,
    ];

    protected $contextIds = [];
    protected $decisions = [];
    protected $statuses = [];
    protected $excludedStatuses = [];
    protected $eventLogMessages = [];
    protected $withDataset = false;
    protected $beginSubmissionInterval;
    protected $endSubmissionInterval;
    protected $beginFinalDecisionInterval;
    protected $endFinalDecisionInterval;

    public function __construct(private string $applicationName)
    {
    }

    public function filterByContexts($contextIds): self
    {
        $this->contextIds = is_array($contextIds) ? $contextIds : [$contextIds];
        return $this;
    }

    public function filterByDecisions($decisions): self
    {
        $this->decisions = is_array($decisions) ? $decisions : [$decisions];
        return $this;
    }

    public function filterByStatuses(array $statuses): self
    {
        $this->statuses = $statuses;
        return $this;
    }

    public function excludeStatuses(array $statuses): self
    {
        $this->excludedStatuses = $statuses;
        return $this;
    }

    public function filterWithDataset(): self
    {
        $this->withDataset = true;
        return $this;
    }

    public function filterWithEventLogs(array $eventLogMessages): self
    {
        $this->eventLogMessages = $eventLogMessages;
        return $this;
    }

    public function withinDateSubmittedInterval(string $beginning, string $ending): self
    {
        $this->beginSubmissionInterval = $beginning;
        $this->endSubmissionInterval = $ending;
        return $this;
    }

    public function withinFinalDecisionDateInterval(string $beginning, string $ending): self
    {
        $this->beginFinalDecisionInterval = $beginning;
        $this->endFinalDecisionInterval = $ending;
        return $this;
    }

    public function getCount(): int
    {
        return $this->getQuery()->count();
    }

    public function getSubmissionIds(): array
    {
        return $this->getQuery()->pluck('s.submission_id')->toArray();
    }

    public function getDataStatementTypes(): array
    {
        $dataStatementTypes = $this->getQuery()
            ->join('publication_settings as ps', 'ps.publication_id', '=', 's.current_publication_id')
            ->where('ps.setting_name', '=', 'dataStatementTypes')
            ->pluck('ps.setting_value')
            ->toArray();

        return array_map(function (?string $dataStatementType): array {
            $decodedTypes = json_decode((string) $dataStatementType, true);
            return is_array($decodedTypes) ? $decodedTypes : [];
        }, $dataStatementTypes);
    }

    public function getQuery(): Builder
    {
        $query = DB::table('submissions as s')
            ->where('s.submission_progress', '');

        if (!empty($this->contextIds)) {
            $query->whereIn('s.context_id', $this->contextIds);
        }

        if (!empty($this->statuses)) {
            $query->whereIn('s.status', $this->statuses);
        }

        if (!empty($this->excludedStatuses)) {
            $query->whereNotIn('s.status', $this->excludedStatuses);
        }

        if (!empty($this->beginSubmissionInterval) && !empty($this->endSubmissionInterval)) {
            $query->whereBetween('s.date_submitted', [$this->beginSubmissionInterval, $this->endSubmissionInterval]);
        }

        if (!empty($this->beginFinalDecisionInterval) && !empty($this->endFinalDecisionInterval)) {
            $this->filterByFinalDecisionDate($query);
        }

        if (!empty($this->decisions)) {
            $this->filterBySubmissionDecisions($query);
        }

        if ($this->withDataset) {
            $query->join('dataverse_studies as ds', 'ds.submission_id', '=', 's.submission_id');
        }

        if (!empty($this->eventLogMessages)) {
            $this->filterBySubmissionEventLogs($query);
        }

        return $query;
    }

    private function filterBySubmissionDecisions(Builder $query): void
    {
        $query->whereIn('s.submission_id', function (Builder $subQuery) {
            $subQuery->select('ed.submission_id')
                ->from('edit_decisions as ed')
                ->whereIn('ed.decision', $this->decisions);
        });
    }

    private function filterBySubmissionEventLogs(Builder $query): void
    {
        $query->whereExists(function (Builder $subQuery) {
            $subQuery->select(DB::raw(1))
                ->from('event_log as el')
                ->where('el.assoc_type', Application::ASSOC_TYPE_SUBMISSION)
                ->whereColumn('el.assoc_id', 's.submission_id')
                ->where(function (Builder $messageQuery) {
                    foreach ($this->eventLogMessages as $message) {
                        $messageQuery->orWhere('el.message', 'like', $message . '%');
                    }
                });
        });
    }

    private function filterByFinalDecisionDate(Builder $query): void
    {
        if ($this->applicationName == DataverseStatsReport::OPS_APP_NAME) {
            $this->filterByOpsFinalDecisionDate($query);
            return;
        }

        $this->joinLatestDecision($query);
        $this->whereLatestDecisionWithinInterval($query, [
            Decision::ACCEPT,
            Decision::DECLINE,
            Decision::INITIAL_DECLINE
        ]);
    }

    private function filterByOpsFinalDecisionDate(Builder $query): void
    {
        $this->joinLatestDecision($query, true);

        $query->where(function (Builder $query) {
            $query->whereIn('s.submission_id', function (Builder $query) {
                $query->from('publications as p')
                    ->select('p.submission_id')
                    ->groupBy('p.submission_id')
                    ->havingRaw(
                        'MIN(p.date_published) BETWEEN ? AND ?',
                        [$this->beginFinalDecisionInterval, $this->endFinalDecisionInterval]
                    );
            });

            $query->orWhere(function (Builder $query) {
                $this->whereLatestDecisionWithinInterval($query, [Decision::DECLINE, Decision::INITIAL_DECLINE]);
            });
        });
    }

    private function joinLatestDecision(Builder $query, bool $leftJoin = false): void
    {
        $joinMethod = $leftJoin ? 'leftJoin' : 'join';

        $query->{$joinMethod}('edit_decisions as last_ed', function ($join) {
            $join->on('last_ed.submission_id', '=', 's.submission_id')
                ->where('last_ed.edit_decision_id', function (Builder $query) {
                    $query->from('edit_decisions as ed2')
                        ->whereColumn('ed2.submission_id', 's.submission_id')
                        ->whereIn('ed2.decision', self::FINAL_DECISION_CHANGES)
                        ->orderBy('ed2.date_decided', 'DESC')
                        ->orderBy('ed2.edit_decision_id', 'DESC')
                        ->limit(1)
                        ->select('ed2.edit_decision_id');
                });
        });
    }

    private function whereLatestDecisionWithinInterval(Builder $query, array $decisions): void
    {
        $query->whereIn('last_ed.decision', $decisions)
            ->whereBetween('last_ed.date_decided', [$this->beginFinalDecisionInterval, $this->endFinalDecisionInterval]);
    }
}
