<?php

namespace APP\plugins\generic\dataverse\report\services\queryBuilders;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use APP\decision\Decision;
use APP\submission\Submission;

class DataverseReportQueryBuilder
{
    protected $contextIds = [];
    protected $decisions = [];

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

    public function getQuery(): Builder
    {
        $query = DB::table('submissions as s');

        if (!empty($this->contextIds)) {
            $query->whereIn('s.context_id', $this->contextIds);
        }

        if (!empty($this->decisions)) {
            $query->whereIn('s.submission_id', function ($subQuery) {
                $subQuery->select('ed.submission_id')
                    ->from('edit_decisions as ed')
                    ->whereIn('ed.decision', $this->decisions);
            });

            $declineDecisions = [Decision::DECLINE, Decision::INITIAL_DECLINE];
            if (count(array_intersect($declineDecisions, $this->decisions))) {
                $query->where('s.status', '=', Submission::STATUS_DECLINED);
            } else {
                $query->where('s.status', '!=', Submission::STATUS_DECLINED);
            }
        }

        $query->where('s.submission_progress', '');

        return $query;
    }

    public function getWithDataset(): Builder
    {
        $query = $this->getQuery();

        $query->leftJoin('dataverse_studies as ds', 'ds.submission_id', '=', 's.submission_id')
            ->whereNotNull('ds.study_id');

        return $query;
    }

    public function countDatasetsWithError(array $messages): int
    {
        $query = $this->getQuery();

        $query->leftJoin('event_log as el', 'el.assoc_id', '=', 's.submission_id')
            ->whereIn('el.message', $messages);

        $query->select(DB::raw('COUNT(DISTINCT s.submission_id) as count'));

        return $query->get()->first()->count;
    }
}
