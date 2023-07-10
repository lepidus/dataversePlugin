<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Query\Builder;

import('classes.workflow.EditorDecisionActionsManager');

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
        $q = Capsule::table('submissions as s');

        if (!empty($this->contextIds)) {
            $q->whereIn('s.context_id', $this->contextIds);
        }

        if (!empty($this->decisions)) {
            $q->leftJoin('edit_decisions as ed', 's.submission_id', '=', 'ed.submission_id')
            ->whereIn('ed.decision', $this->decisions);
        }

        $declineDecisions = [SUBMISSION_EDITOR_DECISION_DECLINE, SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE];
        if (count(array_intersect($declineDecisions, $this->decisions))) {
            $q->where('s.status', '=', STATUS_DECLINED);
        } else {
            $q->where('s.status', '!=', STATUS_DECLINED);
        }

        $q->leftJoin('publications as pi', 'pi.submission_id', '=', 's.submission_id');

        $q->where('s.submission_progress', '=', SUBMISSION_PROGRESS_COMPLETE);

        return $q;
    }
}
