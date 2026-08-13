<?php

namespace APP\plugins\generic\dataverse\report\services\queryBuilders;

use APP\decision\Decision;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;

class DataverseReportQueryBuilder
{
    public const SUBMISSION_PROGRESS_COMPLETE = '';

    protected $contextIds = [];
    protected $decisions = [];
    protected $eventLogs = [];
    protected $statuses = [];
    protected $withDataset = false;
    protected $beginSubmissionInterval;
    protected $endSubmissionInterval;
    protected $beginFinalDecisionInterval;
    protected $endFinalDecisionInterval;

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

    public function filterWithDataset(): self
    {
        $this->withDataset = true;
        return $this;
    }

    public function filterWithEventLogs(array $eventLogMessages): self
    {
        $this->eventLogs = $eventLogMessages;
        return $this;
    }

    public function filterByStatuses(array $statuses): self
    {
        $this->statuses = $statuses;
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
        $query = $this->getQuery();

        $query->leftJoin('publications as p', 's.current_publication_id', '=', 'p.publication_id')
            ->leftJoin('publication_settings as ps', 'p.publication_id', '=', 'ps.publication_id')
            ->where('ps.setting_name', '=', 'dataStatementTypes');

        $dataStatementTypes = $query->pluck('ps.setting_value')->toArray();

        return array_map(
            fn ($dataStatementType) => json_decode($dataStatementType, true),
            $dataStatementTypes
        );
    }

    public function getQuery(): Builder
    {
        $query = DB::table('submissions as s')
            ->where('s.submission_progress', '=', self::SUBMISSION_PROGRESS_COMPLETE);

        if (!empty($this->contextIds)) {
            $query->whereIn('s.context_id', $this->contextIds);
        }

        if (!empty($this->statuses)) {
            $query->whereIn('s.status', $this->statuses);
        }

        if (!empty($this->beginSubmissionInterval) && !empty($this->endSubmissionInterval)) {
            $query->where('s.date_submitted', '>=', $this->beginSubmissionInterval)
                ->where('s.date_submitted', '<=', $this->endSubmissionInterval);
        }

        if (!empty($this->beginFinalDecisionInterval) && !empty($this->endFinalDecisionInterval)) {
            $query->join('edit_decisions as last_ed', function ($join) {
                $join->on('last_ed.submission_id', '=', 's.submission_id')
                    ->where('last_ed.edit_decision_id', function (Builder $query) {
                        $query->from('edit_decisions as ed2')
                            ->where('ed2.submission_id', '=', DB::raw('s.submission_id'))
                            ->orderBy('ed2.date_decided', 'DESC')
                            ->orderBy('ed2.edit_decision_id', 'DESC')
                            ->limit(1)
                            ->select('ed2.edit_decision_id');
                    });
            })
                ->whereIn('last_ed.decision', [
                    Decision::ACCEPT,
                    Decision::DECLINE,
                    Decision::INITIAL_DECLINE
                ])
                ->where('last_ed.date_decided', '>=', $this->beginFinalDecisionInterval)
                ->where('last_ed.date_decided', '<=', $this->endFinalDecisionInterval);
        }

        if (!empty($this->decisions)) {
            $query->leftJoin('edit_decisions as ed', 's.submission_id', '=', 'ed.submission_id')
                ->whereIn('ed.decision', $this->decisions);
        }

        if ($this->withDataset) {
            $query->leftJoin('dataverse_studies as ds', 'ds.submission_id', '=', 's.submission_id')
                ->whereNotNull('ds.study_id');
        }

        if (!empty($this->eventLogs)) {
            $query->leftJoin('event_log as el', 'el.assoc_id', '=', 's.submission_id')
                ->where(function (Builder $query) {
                    foreach ($this->eventLogs as $eventLogMessage) {
                        $query->orWhere('el.message', 'like', "%{$eventLogMessage}%");
                    }
                });
        }

        return $query;
    }
}
