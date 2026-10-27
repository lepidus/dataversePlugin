<?php

declare(strict_types=1);

use APP\plugins\generic\dataverse\classes\migrations\RepairDataverseCoreVersion;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PKP\tests\DatabaseTestCase;

require_once dirname(__DIR__, 2) . '/classes/migrations/RepairDataverseCoreVersion.php';

class RepairDataverseCoreVersionTest extends DatabaseTestCase
{
    private string $product = 'ojs2';
    private $previousCache;
    private bool $cacheWasReplaced = false;
    private ?int $initialTransactionLevel = null;
    private ?string $previousDirectory = null;
    private ?string $directory = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousDirectory = getcwd();
        $this->initialTransactionLevel = DB::transactionLevel();
        DB::beginTransaction();
        DB::table('versions')->delete();

        $this->previousCache = Cache::getFacadeRoot();
        Cache::swap(new Repository(new ArrayStore()));
        $this->cacheWasReplaced = true;
        $this->directory = sys_get_temp_dir() . '/dataverse-version-' . bin2hex(random_bytes(8));
        mkdir($this->directory . '/dbscripts/xml', 0700, true);
        $this->writeCodeVersion('3.4.0.10');
        chdir($this->directory);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->cacheWasReplaced) {
                Cache::swap($this->previousCache);
            }
            if ($this->initialTransactionLevel !== null) {
                while (DB::transactionLevel() > $this->initialTransactionLevel) {
                    DB::rollBack();
                }
            }
            if ($this->previousDirectory !== null) {
                chdir($this->previousDirectory);
            }
            if ($this->directory !== null && is_dir($this->directory)) {
                $versionFile = $this->directory . '/dbscripts/xml/version.xml';
                if (is_file($versionFile)) {
                    unlink($versionFile);
                }
                foreach (['/dbscripts/xml', '/dbscripts', ''] as $suffix) {
                    if (is_dir($this->directory . $suffix)) {
                        rmdir($this->directory . $suffix);
                    }
                }
            }
        } finally {
            parent::tearDown();
        }
    }

    /** @dataProvider supportedProducts */
    public function testRestoresHistoricalCoreAndPreservesOtherProducts(string $product): void
    {
        $this->useProduct($product);
        $this->addVersion('3.4.0.10', 0);
        $this->addVersion('3.4.6.2', 1);
        $this->addVersion('3.4.4.3', 0);
        $this->addVersion('3.4.6.2', 1, 'plugins.generic', 'dataverse');
        $this->addVersion('3.4.6.2', 1, 'core', $this->product === 'ojs2' ? 'ops' : 'ojs2');
        $otherProducts = DB::table('versions')->where('product', '!=', $this->product)->get()->all();
        $original = DB::table('versions')->where('product', $this->product)->where('revision', 0)->first();

        (new RepairDataverseCoreVersion())->up();

        $restored = DB::table('versions')->where('product', $this->product)->first();
        $original->current = 1;
        $this->assertEquals($original, $restored);
        $this->assertSame(1, DB::table('versions')->where('product', $this->product)->count());
        $this->assertEquals($otherProducts, DB::table('versions')->where('product', '!=', $this->product)->get()->all());
    }

    /** @dataProvider supportedProducts */
    public function testHealthyDatabaseAndRepeatedRepairAreUnchanged(string $product): void
    {
        $this->useProduct($product);
        $this->addVersion('3.4.0.9', 0);
        $this->addVersion('3.4.0.10', 1);
        $before = DB::table('versions')->get()->all();
        (new RepairDataverseCoreVersion())->up();
        $this->assertEquals($before, DB::table('versions')->get()->all());

        $this->addVersion('3.4.8.0', 0);
        (new RepairDataverseCoreVersion())->up();
        (new RepairDataverseCoreVersion())->up();
        $this->assertEquals($before, DB::table('versions')->get()->all());
    }

    /** @dataProvider affectedVersions */
    public function testRepairsEachConfirmedXmlValue(string $product, string $version): void
    {
        $this->useProduct($product);
        $this->addVersion('3.4.0.10', 0);
        $this->addVersion($version, 1);
        (new RepairDataverseCoreVersion())->up();
        $this->assertSame(1, DB::table('versions')->count());
        $this->assertSame(1, (int) DB::table('versions')->value('current'));
        $this->assertSame(10, (int) DB::table('versions')->value('build'));
    }

    public function affectedVersions(): array
    {
        return $this->withProducts(array_map(fn ($version) => [$version], [
            '3.4.1.0', '3.4.1.1', '3.4.2.0', '3.4.2.1', '3.4.2.2', '3.4.2.3',
            '3.4.3.0', '3.4.4.0', '3.4.4.1', '3.4.4.3', '3.4.5.1', '3.4.5.2',
            '3.4.6.0', '3.4.6.1', '3.4.6.2', '3.4.8.0',
        ]));
    }

    /** @dataProvider supportedProducts */
    public function testRejectsUnsupportedApplicationWithoutChanges(string $product): void
    {
        $this->useProduct($product);
        $this->addVersion('3.4.0.10', 0);
        $this->addVersion('3.4.6.2', 1);
        file_put_contents(
            $this->directory . '/dbscripts/xml/version.xml',
            '<version><application>omp</application><type>core</type><release>3.4.0.10</release></version>'
        );
        $before = DB::table('versions')->get()->all();
        try {
            (new RepairDataverseCoreVersion())->up();
            $this->fail('Expected an unsupported application failure.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('only OJS', $exception->getMessage());
        }
        $this->assertEquals($before, DB::table('versions')->get()->all());
    }

    /** @dataProvider ambiguousHistories */
    public function testRejectsAmbiguousHistoryWithoutChanges(string $product, array $history): void
    {
        $this->useProduct($product);
        foreach ($history as [$version, $current]) {
            $this->addVersion($version, $current);
        }
        $before = DB::table('versions')->get()->all();
        try {
            (new RepairDataverseCoreVersion())->up();
            $this->fail('An ambiguous history must require manual investigation.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Dataverse', $exception->getMessage());
        }
        $this->assertEquals($before, DB::table('versions')->get()->all());
    }

    public function ambiguousHistories(): array
    {
        return $this->withProducts([
            'missing historical version' => [[['3.4.0.9', 0], ['3.4.6.2', 1]]],
            'unknown newer version' => [[['3.4.0.10', 0], ['3.4.99.0', 1], ['3.4.6.2', 0]]],
            'multiple current versions' => [[['3.4.0.10', 1], ['3.4.6.2', 1]]],
            'no current version' => [[['3.4.0.10', 0], ['3.4.6.2', 0]]],
            'files older than history' => [[['3.4.0.10', 0], ['3.4.0.11', 0], ['3.4.6.2', 1]]],
            'current older than files' => [[['3.4.0.9', 1], ['3.4.0.10', 0], ['3.4.6.2', 0]]],
        ]);
    }

    /** @dataProvider supportedProducts */
    public function testRestoresTransactionWhenDeletionFails(string $product): void
    {
        $this->useProduct($product);
        $this->addVersion('3.4.0.10', 0);
        $this->addVersion('3.4.6.2', 1);
        $before = DB::table('versions')->get()->all();
        $connection = DB::connection();
        $previousDispatcher = $connection->getEventDispatcher();
        $dispatcher = new Dispatcher();
        $dispatcher->listen(QueryExecuted::class, function (QueryExecuted $query): void {
            if (stripos($query->sql, 'delete ') === 0) {
                throw new RuntimeException('Simulated deletion failure.');
            }
        });
        $connection->setEventDispatcher($dispatcher);
        try {
            (new RepairDataverseCoreVersion())->up();
            $this->fail('Expected a database failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated deletion failure.', $exception->getMessage());
        } finally {
            if ($previousDispatcher) {
                $connection->setEventDispatcher($previousDispatcher);
            } else {
                $connection->unsetEventDispatcher();
            }
        }
        $this->assertEquals($before, DB::table('versions')->get()->all());
    }

    /** @dataProvider supportedProducts */
    public function testPreservesSameProductWithDifferentType(string $product): void
    {
        $this->useProduct($product);
        $this->addVersion('3.4.0.10', 0);
        $this->addVersion('3.4.6.2', 1);
        $this->addVersion('3.4.6.2', 1, 'plugins.generic', $this->product);
        $otherType = DB::table('versions')->where('product_type', 'plugins.generic')->first();
        (new RepairDataverseCoreVersion())->up();
        $this->assertEquals($otherType, DB::table('versions')->where('product_type', 'plugins.generic')->first());
    }

    /** @dataProvider supportedProducts */
    public function testDoesNotReintroduceCorruptionOnDowngrade(string $product): void
    {
        $this->useProduct($product);
        $this->expectException(\PKP\install\DowngradeNotSupportedException::class);
        (new RepairDataverseCoreVersion())->down();
    }

    public function supportedProducts(): array
    {
        return ['OJS' => ['ojs2'], 'OPS' => ['ops']];
    }

    private function withProducts(array $cases): array
    {
        $datasets = [];
        foreach ($this->supportedProducts() as $application => [$product]) {
            foreach ($cases as $name => $arguments) {
                $datasets[$application . ': ' . $name] = array_merge([$product], $arguments);
            }
        }
        return $datasets;
    }

    private function useProduct(string $product): void
    {
        $this->product = $product;
        $this->writeCodeVersion('3.4.0.10');
    }

    private function writeCodeVersion(string $version): void
    {
        file_put_contents(
            $this->directory . '/dbscripts/xml/version.xml',
            '<version><application>' . $this->product . '</application><type>core</type><release>'
                . $version . '</release></version>'
        );
    }

    private function addVersion(string $version, int $current, string $type = 'core', ?string $product = null): void
    {
        [$major, $minor, $revision, $build] = array_map('intval', explode('.', $version));
        DB::table('versions')->insert([
            'major' => $major, 'minor' => $minor, 'revision' => $revision, 'build' => $build,
            'current' => $current, 'product_type' => $type, 'product' => $product ?? $this->product,
            'date_installed' => '2026-01-01 00:00:00',
        ]);
    }
}
