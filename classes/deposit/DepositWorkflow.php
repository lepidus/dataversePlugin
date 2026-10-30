<?php

namespace APP\plugins\generic\dataverse\classes\deposit;

use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\classes\exception\UploadNotSentException;
use RuntimeException;

class DepositWorkflow
{
    private DepositRepository $repository;

    public function __construct(DepositRepository $repository)
    {
        $this->repository = $repository;
    }

    public function run(
        int $submissionId,
        array $manifest,
        callable $create,
        callable $upload,
        callable $complete,
        ?callable $update = null,
        ?callable $discard = null
    ): void {
        $operation = $this->repository->reserve($submissionId, $manifest);
        if (json_decode($operation['manifest'], true, 512, JSON_THROW_ON_ERROR) !== $manifest) {
            $operation = $this->adoptManifest($submissionId, $operation, $manifest, $discard);
        }
        if ($operation['state'] === 'complete') {
            return;
        }
        $state = $operation['state'];
        $persistentId = $operation['persistent_id'];
        if ($state === 'reserved') {
            $this->repository->transition($submissionId, 'reserved', 'creating');
            try {
                $persistentId = $create();
            } catch (DataverseException $e) {
                if ($e->wasRejected()) {
                    $this->repository->transition($submissionId, 'creating', 'retired');
                }
                throw $e;
            }
            if (!is_string($persistentId) || $persistentId === '') {
                throw new RuntimeException('The dataset creation response has no persistent identifier.');
            }
            $this->repository->transition($submissionId, 'creating', 'ready', ['persistent_id' => $persistentId]);
        } elseif ($state === 'ready' && $persistentId && $update) {
            $update($persistentId);
        }
        if (!in_array($state, ['reserved', 'ready'], true) || !$persistentId) {
            throw new RuntimeException('The previous remote operation has no confirmed result; reconciliation is required.');
        }
        $receipts = json_decode($operation['receipts'], true, 512, JSON_THROW_ON_ERROR);
        foreach ($manifest as $key => $file) {
            if (isset($receipts[$key])) {
                continue;
            }
            $this->repository->transition($submissionId, 'ready', 'uploading');
            try {
                $receipt = $upload($persistentId, $key);
            } catch (UploadNotSentException $e) {
                $this->repository->transition($submissionId, 'uploading', 'ready');
                throw $e;
            }
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

    private function adoptManifest(int $submissionId, array $operation, array $manifest, ?callable $discard): array
    {
        $storedManifest = json_decode($operation['manifest'], true, 512, JSON_THROW_ON_ERROR);
        $encodedManifest = json_encode($manifest, JSON_THROW_ON_ERROR);

        if ($operation['state'] === 'reserved') {
            $this->repository->transition($submissionId, 'reserved', 'reserved', ['manifest' => $encodedManifest]);
            return array_merge($operation, ['manifest' => $encodedManifest]);
        }

        $sameRepository = array_unique(array_column($storedManifest, 'repository'))
            === array_unique(array_column($manifest, 'repository'));
        if ($operation['state'] !== 'ready' || !$operation['persistent_id'] || !$sameRepository || !$discard) {
            throw new RuntimeException('The deposit file selection changed; reconciliation is required.');
        }

        $discard($operation['persistent_id']);
        $this->repository->transition($submissionId, 'ready', 'retired');

        return $this->repository->reserve($submissionId, $manifest);
    }
}
