<?php

use PHPUnit\Framework\TestCase;

import('plugins.generic.dataverse.classes.deposit.DepositRepository');
import('plugins.generic.dataverse.classes.deposit.DepositWorkflow');

class MemoryDepositRepository extends DepositRepository
{
    public ?array $operation = null;
    public bool $failFinalize = false;

    public function reserve(int $submissionId, array $manifest): array
    {
        if ($this->operation === null) {
            $this->operation = [
                'state' => 'reserved',
                'persistent_id' => null,
                'manifest' => json_encode($manifest),
                'receipts' => '{}',
            ];
        }
        return $this->operation;
    }

    public function transition(int $submissionId, string $from, string $to, array $values = []): void
    {
        if ($this->operation['state'] !== $from) {
            throw new RuntimeException('Concurrent mutation');
        }
        $this->operation = array_merge($this->operation, $values, ['state' => $to]);
    }

    public function complete(int $submissionId, callable $complete): void
    {
        if ($this->failFinalize) {
            throw new RuntimeException('Database unavailable');
        }
        $complete();
        $this->transition($submissionId, 'ready', 'complete');
    }
}

class DepositWorkflowTest extends TestCase
{
    private array $manifest = [['sourceId' => 4, 'sha256' => 'abc', 'size' => 12, 'name' => 'data.zip']];

    public function testLostCreateResponseDoesNotCreateAgain(): void
    {
        $repository = new MemoryDepositRepository();
        $workflow = new DepositWorkflow($repository);
        $creates = 0;
        $create = function () use (&$creates): string {
            $creates++;
            throw new RuntimeException('Response lost after remote creation');
        };
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $workflow->run(1, $this->manifest, $create, fn () => $this->fail('Upload'), fn () => $this->fail('Complete'));
                $this->fail('Must require reconciliation');
            } catch (RuntimeException $e) {
                $this->assertSame('creating', $repository->operation['state']);
            }
        }
        $this->assertSame(1, $creates);
    }

    public function testLostUploadResponsePreservesDoiAndNeverRepeatsUpload(): void
    {
        $repository = new MemoryDepositRepository();
        $workflow = new DepositWorkflow($repository);
        $uploads = 0;
        $upload = function () use (&$uploads): array {
            $uploads++;
            throw new RuntimeException('Response lost after ZIP extraction');
        };
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $workflow->run(1, $this->manifest, fn () => 'doi:10.1234/ABC', $upload, fn () => $this->fail('Complete'));
                $this->fail('Must require reconciliation');
            } catch (RuntimeException $e) {
                $this->assertSame('uploading', $repository->operation['state']);
                $this->assertSame('doi:10.1234/ABC', $repository->operation['persistent_id']);
            }
        }
        $this->assertSame(1, $uploads);
    }

    public function testRetryAfterLocalFailureUsesSameDoiAndConfirmedReceipts(): void
    {
        $repository = new MemoryDepositRepository();
        $repository->failFinalize = true;
        $workflow = new DepositWorkflow($repository);
        try {
            $workflow->run(1, $this->manifest, fn () => 'doi:10.1234/ABC', fn () => [['id' => 10], ['id' => 11]], fn () => null);
            $this->fail('Database error expected');
        } catch (RuntimeException $e) {
            $this->assertSame('ready', $repository->operation['state']);
        }
        $repository->failFinalize = false;
        $workflow->run(
            1,
            $this->manifest,
            fn () => $this->fail('Duplicate dataset'),
            fn () => $this->fail('Duplicate upload'),
            fn ($doi) => $this->assertSame('doi:10.1234/ABC', $doi)
        );
        $this->assertSame('complete', $repository->operation['state']);
        $workflow->run(1, $this->manifest, fn () => $this->fail(), fn () => $this->fail(), fn () => $this->fail());
    }

    public function testChangedSourceSelectionCannotResumePartialDeposit(): void
    {
        $repository = new MemoryDepositRepository();
        $repository->reserve(1, $this->manifest);
        $workflow = new DepositWorkflow($repository);
        $this->expectException(RuntimeException::class);
        $workflow->run(1, [['sourceId' => 8]], fn () => $this->fail(), fn () => $this->fail(), fn () => $this->fail());
    }

    public function testCompletedDepositRejectsChangedSourceSelection(): void
    {
        $repository = new MemoryDepositRepository();
        $workflow = new DepositWorkflow($repository);
        $workflow->run(
            1,
            $this->manifest,
            function (): string {
                return 'doi:10.1234/ABC';
            },
            function (): array {
                return [['id' => 10]];
            },
            function (): void {
            }
        );

        $this->expectException(RuntimeException::class);
        $workflow->run(
            1,
            [['sourceId' => 8]],
            function (): void {
                $this->fail('Duplicate dataset');
            },
            function (): void {
                $this->fail('Duplicate upload');
            },
            function (): void {
                $this->fail('Duplicate finalization');
            }
        );
    }
}
