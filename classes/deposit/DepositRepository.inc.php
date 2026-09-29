<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class DepositRepository
{
    /** @var array<int, int> */
    private $revisions = [];

    public function reserve(int $submissionId, array $manifest): array
    {
        return Capsule::connection()->transaction(function () use ($submissionId, $manifest) {
            $this->lockSubmission($submissionId);
            $operation = Capsule::table('dataverse_deposits')->where('submission_id', $submissionId)->first();
            if ($operation && $operation->state !== 'retired') {
                $this->revisions[$submissionId] = (int) $operation->revision;
                return (array) $operation;
            }
            if (Capsule::table('dataverse_studies')->where('submission_id', $submissionId)->exists()) {
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
            Capsule::table('dataverse_deposits')->updateOrInsert(['submission_id' => $submissionId], $operation);
            $this->revisions[$submissionId] = $operation['revision'];
            return $operation;
        });
    }

    public function transition(int $submissionId, string $from, string $to, array $values = []): void
    {
        $affected = Capsule::table('dataverse_deposits')->where('submission_id', $submissionId)
            ->where('state', $from)->where('revision', $this->revisions[$submissionId])
            ->update(array_merge($values, ['state' => $to, 'revision' => $this->revisions[$submissionId] + 1]));
        if ($affected !== 1) {
            throw new RuntimeException('Another request owns this deposit or its result needs reconciliation.');
        }
        $this->revisions[$submissionId]++;
    }

    public function complete(int $submissionId, callable $complete): void
    {
        Capsule::connection()->transaction(function () use ($submissionId, $complete) {
            $this->lockSubmission($submissionId);
            $this->transition($submissionId, 'ready', 'finalizing');
            if (Capsule::table('dataverse_studies')->where('submission_id', $submissionId)->exists()) {
                throw new RuntimeException('A dataset is already associated with this submission.');
            }
            $complete();
            $this->transition($submissionId, 'finalizing', 'complete');
        });
    }

    public function retire(int $submissionId, callable $removeAssociation): void
    {
        Capsule::connection()->transaction(function () use ($submissionId, $removeAssociation) {
            $this->lockSubmission($submissionId);
            $operation = Capsule::table('dataverse_deposits')->where('submission_id', $submissionId)->first();
            if ($operation && !in_array($operation->state, ['complete', 'retired'], true)) {
                throw new RuntimeException('An incomplete deposit must be reconciled before removing its association.');
            }
            $removeAssociation();
            if ($operation) {
                Capsule::table('dataverse_deposits')->where('submission_id', $submissionId)->update([
                    'state' => 'retired',
                    'revision' => (int) $operation->revision + 1,
                ]);
            }
        });
    }

    public function lockSubmission(int $submissionId): void
    {
        if (!Capsule::table('submissions')->where('submission_id', $submissionId)->lockForUpdate()->first()) {
            throw new RuntimeException('Submission not found.');
        }
    }
}
