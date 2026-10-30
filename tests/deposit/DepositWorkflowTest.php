<?php

use APP\plugins\generic\dataverse\classes\deposit\DepositRepository;
use APP\plugins\generic\dataverse\classes\deposit\DepositWorkflow;
use APP\plugins\generic\dataverse\classes\exception\DataverseException;
use APP\plugins\generic\dataverse\classes\exception\UploadNotSentException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class MemoryDepositRepository extends DepositRepository
{
    public ?array $operation = null;
    public bool $failFinalize = false;

    public function reserve(int $submissionId, array $manifest): array
    {
        if ($this->operation === null || $this->operation['state'] === 'retired') {
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

    public function testUploadThatWasNotSentCanBeRetriedOnTheSameDataset(): void
    {
        $repository = new MemoryDepositRepository();
        $workflow = new DepositWorkflow($repository);
        $creates = 0;
        $uploads = 0;
        $create = function () use (&$creates): string {
            $creates++;
            return 'doi:10.1234/ABC';
        };
        $upload = function (string $persistentId) use (&$uploads): array {
            $uploads++;
            if ($uploads === 1) {
                throw new UploadNotSentException('data.zip', new DataverseException('Ingest still running'));
            }
            return [['id' => 10]];
        };

        try {
            $workflow->run(1, $this->manifest, $create, $upload, fn () => $this->fail('Complete'));
            $this->fail('The upload was not sent');
        } catch (UploadNotSentException $e) {
            $this->assertSame('ready', $repository->operation['state']);
        }
        $workflow->run(1, $this->manifest, $create, $upload, fn ($doi) => $this->assertSame('doi:10.1234/ABC', $doi));

        $this->assertSame('complete', $repository->operation['state']);
        $this->assertSame(1, $creates);
        $this->assertSame(2, $uploads);
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

    public function testCompletedOperationRejectsDifferentUnsentSelection(): void
    {
        $repository = new MemoryDepositRepository();
        $workflow = new DepositWorkflow($repository);
        $workflow->run(1, $this->manifest, fn () => 'doi:10.1234/ABC', fn () => [['id' => 10]], fn () => null);
        $this->expectException(RuntimeException::class);
        $workflow->run(1, [['sourceId' => 99]], fn () => $this->fail(), fn () => $this->fail(), fn () => $this->fail());
    }

    public function testChangedSourceSelectionIsAdoptedBeforeTheDatasetIsCreated(): void
    {
        $repository = new MemoryDepositRepository();
        $repository->reserve(1, $this->manifest);
        $workflow = new DepositWorkflow($repository);
        $changedManifest = [['sourceId' => 8, 'sha256' => 'def', 'size' => 3, 'name' => 'other.csv']];

        $workflow->run(1, $changedManifest, fn () => 'doi:10.1234/ABC', fn () => [['id' => 10]], fn () => null);

        $this->assertSame('complete', $repository->operation['state']);
        $this->assertSame($changedManifest, json_decode($repository->operation['manifest'], true));
    }

    public function testRejectedCreationCanBeTriedAgain(): void
    {
        $repository = new MemoryDepositRepository();
        $workflow = new DepositWorkflow($repository);
        $creates = 0;
        $create = function () use (&$creates): string {
            $creates++;
            if ($creates === 1) {
                throw $this->rejectedRequest();
            }
            return 'doi:10.1234/ABC';
        };

        try {
            $workflow->run(1, $this->manifest, $create, fn () => [['id' => 10]], fn () => $this->fail('Complete'));
            $this->fail('Dataverse rejected the dataset');
        } catch (DataverseException $e) {
            $this->assertSame('retired', $repository->operation['state']);
        }
        $workflow->run(1, $this->manifest, $create, fn () => [['id' => 10]], fn () => null);

        $this->assertSame('complete', $repository->operation['state']);
        $this->assertSame(2, $creates);
    }

    public function testResumedDepositSendsTheMetadataAgain(): void
    {
        $repository = new MemoryDepositRepository();
        $workflow = new DepositWorkflow($repository);
        $updates = [];
        $uploads = 0;
        $upload = function () use (&$uploads): array {
            $uploads++;
            if ($uploads === 1) {
                throw new UploadNotSentException('data.zip', new DataverseException('Ingest still running'));
            }
            return [['id' => 10]];
        };
        $update = function (string $persistentId) use (&$updates): void {
            $updates[] = $persistentId;
        };

        try {
            $workflow->run(1, $this->manifest, fn () => 'doi:10.1234/ABC', $upload, fn () => null, $update);
        } catch (UploadNotSentException $e) {
        }
        $workflow->run(1, $this->manifest, fn () => $this->fail('Duplicate dataset'), $upload, fn () => null, $update);

        $this->assertSame(['doi:10.1234/ABC'], $updates);
        $this->assertSame('complete', $repository->operation['state']);
    }

    public function testChangedSourceSelectionReplacesThePartialDataset(): void
    {
        $repository = new MemoryDepositRepository();
        $workflow = new DepositWorkflow($repository);
        $discarded = [];
        $persistentIds = ['doi:10.1234/FIRST', 'doi:10.1234/SECOND'];
        $create = function () use (&$persistentIds): string {
            return array_shift($persistentIds);
        };
        $changedManifest = [['sourceId' => 8, 'sha256' => 'def', 'size' => 3, 'name' => 'other.csv']];
        $discard = function (string $persistentId) use (&$discarded): void {
            $discarded[] = $persistentId;
        };

        try {
            $workflow->run(
                1,
                $this->manifest,
                $create,
                fn () => throw new UploadNotSentException('data.zip', new DataverseException('Ingest still running')),
                fn () => null
            );
        } catch (UploadNotSentException $e) {
        }
        $workflow->run(1, $changedManifest, $create, fn () => [['id' => 11]], fn () => null, null, $discard);

        $this->assertSame(['doi:10.1234/FIRST'], $discarded);
        $this->assertSame('complete', $repository->operation['state']);
        $this->assertSame('doi:10.1234/SECOND', $repository->operation['persistent_id']);
        $this->assertSame($changedManifest, json_decode($repository->operation['manifest'], true));
    }

    public function testPartialDatasetInAnotherRepositoryNeedsReconciliation(): void
    {
        $repository = new MemoryDepositRepository();
        $workflow = new DepositWorkflow($repository);
        $manifest = [['sourceId' => 4, 'repository' => 'https://old.example/dataverse/a']];
        try {
            $workflow->run(
                1,
                $manifest,
                fn () => 'doi:10.1234/ABC',
                fn () => throw new UploadNotSentException('data.zip', new DataverseException('Ingest still running')),
                fn () => null
            );
        } catch (UploadNotSentException $e) {
        }

        $this->expectException(RuntimeException::class);
        $workflow->run(
            1,
            [['sourceId' => 4, 'repository' => 'https://new.example/dataverse/b']],
            fn () => $this->fail('Duplicate dataset'),
            fn () => $this->fail('Upload'),
            fn () => $this->fail('Complete'),
            null,
            fn () => $this->fail('The dataset in the previous repository was discarded')
        );
    }

    private function rejectedRequest(): DataverseException
    {
        $request = new Request('POST', 'https://dataverse.example/api/dataverses/test/datasets');
        $response = new Response(400, [], '{"message":"Invalid license"}');

        return DataverseException::fromTransferException(new ClientException('Invalid license', $request, $response));
    }
}
