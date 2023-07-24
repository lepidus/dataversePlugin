<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Query\Builder;

import('classes.workflow.EditorDecisionActionsManager');
import('lib.pkp.classes.submission.PKPSubmission');

define('SUBMISSION_PROGRESS_COMPLETE', 0);

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
        $query = Capsule::table('submissions as s');

        if (!empty($this->contextIds)) {
            $query->whereIn('s.context_id', $this->contextIds);
        }

        if (!empty($this->decisions)) {
            $query->leftJoin('edit_decisions as ed', 's.submission_id', '=', 'ed.submission_id')
                ->whereIn('ed.decision', $this->decisions);

            $declineDecisions = [SUBMISSION_EDITOR_DECISION_DECLINE, SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE];
            if (count(array_intersect($declineDecisions, $this->decisions))) {
                $query->where('s.status', '=', STATUS_DECLINED);
            } else {
                $query->where('s.status', '!=', STATUS_DECLINED);
            }
        }

        $query->leftJoin('publications as pi', 'pi.submission_id', '=', 's.submission_id');

        $query->where('s.submission_progress', '=', SUBMISSION_PROGRESS_COMPLETE);

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

        $query->select(Capsule::raw('COUNT(DISTINCT s.submission_id) as count'));

        return $query->get()->first()->count;
    }
}
