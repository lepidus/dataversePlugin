<?php

/** Persist intent before each remote mutation. An unconfirmed mutation is never repeated. */
class DepositWorkflow
{
    private DepositRepository $repository;

    public function __construct(DepositRepository $repository)
    {
        $this->repository = $repository;
    }

    public function run(int $submissionId, array $manifest, callable $create, callable $upload, callable $complete): void
    {
        $operation = $this->repository->reserve($submissionId, $manifest);
        if (json_decode($operation['manifest'], true, 512, JSON_THROW_ON_ERROR) !== $manifest) {
            throw new RuntimeException('The deposit file selection changed; reconciliation is required.');
        }
        if ($operation['state'] === 'complete') {
            return;
        }
        $state = $operation['state'];
        $persistentId = $operation['persistent_id'];
        if ($state === 'reserved') {
            $this->repository->transition($submissionId, 'reserved', 'creating');
            $persistentId = $create();
            if (!is_string($persistentId) || $persistentId === '') {
                throw new RuntimeException('The dataset creation response has no persistent identifier.');
            }
            $this->repository->transition($submissionId, 'creating', 'ready', ['persistent_id' => $persistentId]);
            $state = 'ready';
        }
        if ($state !== 'ready' || !$persistentId) {
            throw new RuntimeException('The previous remote operation has no confirmed result; reconciliation is required.');
        }
        $receipts = json_decode($operation['receipts'], true, 512, JSON_THROW_ON_ERROR);
        foreach ($manifest as $key => $file) {
            if (isset($receipts[$key])) {
                continue;
            }
            $this->repository->transition($submissionId, 'ready', 'uploading');
            $receipt = $upload($persistentId, $key);
            if (!$receipt) {
                throw new RuntimeException('The file upload response has no receipt.');
            }
            $receipts[$key] = $receipt;
            $this->repository->transition($submissionId, 'uploading', 'ready', [
                'receipts' => json_encode($receipts, JSON_THROW_ON_ERROR),
            ]);
        }
        $this->repository->complete($submissionId, function () use ($complete, $persistentId) {
            $complete($persistentId);
        });
    }
}
