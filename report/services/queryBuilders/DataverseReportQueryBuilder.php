<?php

namespace APP\plugins\generic\dataverse\report\services\queryBuilders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use APP\decision\Decision;
use APP\submission\Submission;

class DataverseReportQueryBuilder
{
    public const SUBMISSION_PROGRESS_COMPLETE = 0;

    protected $contextIds = [];
    protected $decisions = [];
    protected $eventLogs = [];
    protected $statuses = [];
    protected $withDataset = false;

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

    public function getCount(): int
    {
        return $this->getQuery()->count();
    }

    public function getSubmissionIds(): array
    {
        return $this->getQuery()->pluck('s.submission_id')->toArray();
    }

    public function getQuery(): Builder
    {
        $query = DB::table('submissions as s');

        if (!empty($this->contextIds)) {
            $query->whereIn('s.context_id', $this->contextIds);
        }

        if (!empty($this->decisions)) {
            $query->leftJoin('edit_decisions as ed', 's.submission_id', '=', 'ed.submission_id')
                ->whereIn('ed.decision', $this->decisions);
        }

        if ($this->withDataset) {
            $query->leftJoin('dataverse_studies as ds', 'ds.submission_id', '=', 's.submission_id')
                ->whereNotNull('ds.study_id');
        }

        if (!empty($this->statuses)) {
            $query->whereIn('s.status', $this->statuses);
        }

        if (!empty($this->eventLogs)) {
            $query->leftJoin('event_log as el', 'el.assoc_id', '=', 's.submission_id')
                ->whereIn('el.message', $this->eventLogs);
        }

        $query->leftJoin('publications as pi', 'pi.submission_id', '=', 's.submission_id');

        $query->where('s.submission_progress', '=', self::SUBMISSION_PROGRESS_COMPLETE);

        return $query;
    }
}
