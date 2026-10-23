<?php

declare(strict_types=1);

use APP\core\Application;
use APP\plugins\generic\dataverse\classes\migrations\RepairCoreVersionMigration;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use PKP\install\Installer;
use PKP\site\VersionCheck;
use PKP\tests\DatabaseTestCase;

class RepairCoreVersionMigrationTest extends DatabaseTestCase
{
    private array $messages = [];
    private string $product;
    private string $codeRelease;
    private array $codeParts;
    private $previousMode;

    protected function getAffectedTables(): array
    {
        return ['versions'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousMode = getenv('DATAVERSE_CORE_VERSION_REPAIR');
        putenv('DATAVERSE_CORE_VERSION_REPAIR=apply');
        $this->product = Application::getName();
        $this->codeRelease = VersionCheck::getCurrentCodeVersion()->getVersionString();
        $this->codeParts = array_map('intval', explode('.', $this->codeRelease));
        $this->messages = [];

        DB::table('versions')->delete();
        DB::table('versions')->insert([
            $this->row(10, [3, 3, 0, 22], 0, '2024-01-01 00:00:00'),
            $this->row(20, $this->codeParts, 0, '2025-01-01 00:00:00'),
            $this->row(30, [3, 4, 6, 2], 1, '2026-09-25 10:55:31'),
            $this->row(31, [3, 4, 6, 2], 1, '2026-09-25 10:55:31', 'plugins.generic', 'dataverse'),
        ]);
    }

    protected function tearDown(): void
    {
        putenv($this->previousMode === false ? 'DATAVERSE_CORE_VERSION_REPAIR' : 'DATAVERSE_CORE_VERSION_REPAIR=' . $this->previousMode);
        parent::tearDown();
    }

    public function testCheckIsTheDefaultAndDoesNotChangeAnyRows(): void
    {
        putenv('DATAVERSE_CORE_VERSION_REPAIR');
        $before = $this->snapshot();
        $this->migration()->up();
        self::assertEquals($before, $this->snapshot());
        self::assertStringContainsString('CHECK ONLY', $this->messages[0]);
    }

    public function testRepairsOnlyTheConfirmedCoreRecordsAndIsIdempotent(): void
    {
        $before = $this->snapshot();
        $expected = array_values(array_filter($before, fn ($row): bool => $row['version_id'] != 30));
        foreach ($expected as &$row) {
            if ($row['version_id'] == 20) {
                $row['current'] = 1;
            }
        }
        unset($row);

        $this->migration()->up();
        self::assertEquals($expected, $this->snapshot());
        self::assertStringContainsString($this->codeRelease, $this->messages[0]);
        $this->migration()->up();
        self::assertEquals($expected, $this->snapshot());
        self::assertStringContainsString('already matches', $this->messages[1]);
    }

    public function testHistoricalDataverseRecordStillIdentifiesTheEvent(): void
    {
        DB::table('versions')->where('version_id', 31)->update(['current' => 0]);
        DB::table('versions')->insert($this->row(40, [3, 4, 8, 3], 1, '2026-10-23 00:00:00', 'plugins.generic', 'dataverse'));
        $plugins = DB::table('versions')->where('product_type', 'plugins.generic')->orderBy('version_id')->get()->toArray();
        $this->migration()->up();
        self::assertEquals($plugins, DB::table('versions')->where('product_type', 'plugins.generic')->orderBy('version_id')->get()->toArray());
        self::assertEquals(1, DB::table('versions')->where('version_id', 20)->value('current'));
    }

    public function testDoesNotChangeTheOtherApplicationOrOtherPlugins(): void
    {
        $otherProduct = $this->product === 'ojs2' ? 'ops' : 'ojs2';
        DB::table('versions')->insert($this->row(50, $this->codeParts, 1, '2025-01-01 00:00:00', 'core', $otherProduct));
        DB::table('versions')->insert($this->row(51, [1, 0, 0, 0], 1, '2025-01-01 00:00:00', 'plugins.generic', 'otherPlugin'));
        $before = DB::table('versions')->whereIn('version_id', [31, 50, 51])->orderBy('version_id')->get()->toArray();
        $this->migration()->up();
        self::assertEquals($before, DB::table('versions')->whereIn('version_id', [31, 50, 51])->orderBy('version_id')->get()->toArray());
    }

    /** @dataProvider ambiguousHistories */
    public function testRefusesAmbiguousHistoryWithoutChangingRows(string $scenario): void
    {
        switch ($scenario) {
            case 'missing-code-version':
                DB::table('versions')->where('version_id', 20)->delete();
                break;
            case 'code-mismatch':
                DB::table('versions')->where('version_id', 20)->update(['build' => $this->codeParts[3] + 1]);
                break;
            case 'multiple-active-core':
                DB::table('versions')->where('version_id', 20)->update(['current' => 1]);
                break;
            case 'inactive-incorrect-version':
                DB::table('versions')->where('version_id', 30)->update(['current' => 0]);
                DB::table('versions')->where('version_id', 20)->update(['current' => 1]);
                break;
            case 'later-core-record':
                DB::table('versions')->insert($this->row(40, [3, 5, 0, 1], 0, '2026-10-01 00:00:00'));
                break;
            case 'intermediate-core-record':
                DB::table('versions')->insert($this->row(25, [3, 4, 0, $this->codeParts[3] + 2], 0, '2026-01-01 00:00:00'));
                break;
            case 'missing-plugin-record':
                DB::table('versions')->where('version_id', 31)->delete();
                break;
            case 'nonadjacent-plugin-record':
                DB::table('versions')->where('version_id', 31)->update(['version_id' => 32]);
                break;
            case 'different-plugin-date':
                DB::table('versions')->where('version_id', 31)->update(['date_installed' => '2026-09-25 10:55:32']);
                break;
            case 'wrong-plugin-class':
                DB::table('versions')->where('version_id', 31)->update(['product_class_name' => 'OtherPlugin']);
                break;
            case 'wrong-plugin-loading':
                DB::table('versions')->where('version_id', 31)->update(['lazy_load' => 0]);
                break;
            case 'wrong-plugin-scope':
                DB::table('versions')->where('version_id', 31)->update(['sitewide' => 1]);
                break;
            case 'invalid-core-current-flag':
                DB::table('versions')->where('version_id', 10)->update(['current' => 2]);
                break;
            case 'wrong-core-metadata':
                DB::table('versions')->where('version_id', 30)->update(['sitewide' => 0]);
                break;
            case 'unordered-dates':
                DB::table('versions')->where('version_id', 20)->update(['date_installed' => '2026-09-25 10:55:31']);
                break;
            default:
                self::fail('Unknown test scenario');
        }

        $before = $this->snapshot();
        try {
            $this->migration()->up();
            self::fail('Ambiguous history must be refused.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('refusing repair', $e->getMessage());
        }
        self::assertEquals($before, $this->snapshot());
    }

    public static function ambiguousHistories(): array
    {
        return array_map(fn (string $scenario): array => [$scenario], [
            'missing-code-version', 'code-mismatch', 'multiple-active-core', 'inactive-incorrect-version',
            'later-core-record', 'intermediate-core-record', 'missing-plugin-record', 'nonadjacent-plugin-record',
            'different-plugin-date', 'wrong-plugin-class', 'wrong-plugin-loading', 'wrong-plugin-scope',
            'invalid-core-current-flag', 'wrong-core-metadata', 'unordered-dates',
        ]);
    }

    public function testRollsBackTheDeletionIfTheUpdateFails(): void
    {
        $connection = DB::connection();
        $originalDispatcher = $connection->getEventDispatcher();
        $dispatcher = new Dispatcher();
        $dispatcher->listen(QueryExecuted::class, function (QueryExecuted $query): void {
            if (stripos(ltrim($query->sql), 'update ') === 0) {
                throw new RuntimeException('Expected database failure during restoration.');
            }
        });
        $before = $this->snapshot();
        $connection->setEventDispatcher($dispatcher);
        try {
            $this->migration()->up();
            self::fail('The injected failure must abort the repair.');
        } catch (RuntimeException $e) {
            self::assertSame('Expected database failure during restoration.', $e->getMessage());
        } finally {
            if ($originalDispatcher) {
                $connection->setEventDispatcher($originalDispatcher);
            } else {
                $connection->unsetEventDispatcher();
            }
        }
        self::assertEquals($before, $this->snapshot());
        self::assertSame([], $this->messages);
    }

    public function testRefusesAutomaticUpgradeExecution(): void
    {
        $installer = $this->installer();
        $installer->setCurrentVersion(VersionCheck::getCurrentCodeVersion());
        $before = $this->snapshot();
        try {
            (new RepairCoreVersionMigration($installer, []))->up();
            self::fail('Automatic execution must be refused.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('Run this repair separately', $e->getMessage());
        }
        self::assertEquals($before, $this->snapshot());
    }

    private function migration(): RepairCoreVersionMigration
    {
        return new RepairCoreVersionMigration($this->installer(), []);
    }

    private function installer(): Installer
    {
        $installer = $this->getMockBuilder(Installer::class)->disableOriginalConstructor()->onlyMethods(['log'])->getMock();
        $installer->isPlugin = false;
        $installer->method('log')->willReturnCallback(function (string $message): void {
            $this->messages[] = $message;
        });
        return $installer;
    }

    private function snapshot(): array
    {
        return DB::table('versions')->orderBy('version_id')->get()->map(fn ($row): array => (array) $row)->all();
    }

    private function row(int $id, array $parts, int $current, string $date, string $type = 'core', ?string $product = null): array
    {
        return [
            'version_id' => $id, 'major' => $parts[0], 'minor' => $parts[1], 'revision' => $parts[2], 'build' => $parts[3],
            'date_installed' => $date, 'current' => $current, 'product_type' => $type, 'product' => $product ?? $this->product,
            'product_class_name' => $type === 'core' ? '' : 'DataversePlugin', 'lazy_load' => $type === 'core' ? 0 : 1,
            'sitewide' => $type === 'core' ? 1 : 0,
        ];
    }
}
