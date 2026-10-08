<?php

namespace APP\plugins\generic\dataverse\classes\deposit;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class DepositRepository
{
    private array $revisions = [];

    public function reserve(int $submissionId, array $manifest): array
    {
        return DB::transaction(function () use ($submissionId, $manifest) {
            $this->lockSubmission($submissionId);
            $operation = DB::table('dataverse_deposits')->where('submission_id', $submissionId)->first();
            if ($operation && $operation->state !== 'retired') {
                $this->revisions[$submissionId] = (int) $operation->revision;
                return (array) $operation;
            }
            if (DB::table('dataverse_studies')->where('submission_id', $submissionId)->exists()) {
                throw new RuntimeException('A dataset is already associated with this submission.');
            }
            $operation = [
                'submission_id' => $submissionId,
                'revision' => $operation ? (int) $operation->revision + 1 : 0,
                'state' => 'reserved',
                'persistent_id' => null,
                'manifest' => json_encode($manifest, JSON_THROW_ON_ERROR),
                'receipts' => '{}',
            ];
            DB::table('dataverse_deposits')->updateOrInsert(['submission_id' => $submissionId], $operation);
            $this->revisions[$submissionId] = $operation['revision'];
            return $operation;
        });
    }

    public function transition(int $submissionId, string $from, string $to, array $values = []): void
    {
        $affected = DB::table('dataverse_deposits')
            ->where('submission_id', $submissionId)
            ->where('revision', $this->revisions[$submissionId])
            ->where('state', $from)
            ->update([
                ...$values,
                'state' => $to,
                'revision' => $this->revisions[$submissionId] + 1
            ]);
        if ($affected !== 1) {
            throw new RuntimeException('Another request owns this deposit or its result needs reconciliation.');
        }
        $this->revisions[$submissionId]++;
    }

    public function complete(int $submissionId, callable $complete): void
    {
        DB::transaction(function () use ($submissionId, $complete) {
            $this->lockSubmission($submissionId);
            $this->transition($submissionId, 'ready', 'finalizing');
            if (DB::table('dataverse_studies')->where('submission_id', $submissionId)->exists()) {
                throw new RuntimeException('A dataset is already associated with this submission.');
            }
            $complete();
            $this->transition($submissionId, 'finalizing', 'complete');
        });
    }

    public function retire(int $submissionId, callable $removeAssociation): void
    {
        DB::transaction(function () use ($submissionId, $removeAssociation) {
            $this->lockSubmission($submissionId);
            $operation = DB::table('dataverse_deposits')->where('submission_id', $submissionId)->first();
            if ($operation && !in_array($operation->state, ['complete', 'retired'], true)) {
                throw new RuntimeException('An incomplete deposit must be reconciled before removing its association.');
            }
            $removeAssociation();
            if ($operation) {
                DB::table('dataverse_deposits')
                    ->where('submission_id', $submissionId)
                    ->update([
                        'state' => 'retired',
                        'revision' => (int) $operation->revision + 1,
                    ]);
            }
        });
    }

    public function lockSubmission(int $submissionId): void
    {
        if (!DB::table('submissions')->where('submission_id', $submissionId)->lockForUpdate()->first()) {
            throw new RuntimeException('Submission not found.');
        }
    }
}
