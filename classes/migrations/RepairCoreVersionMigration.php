<?php

declare(strict_types=1);

namespace APP\plugins\generic\dataverse\classes\migrations;

use APP\core\Application;
use Illuminate\Support\Facades\DB;
use PKP\migration\Migration;
use PKP\site\VersionCheck;
use RuntimeException;

/**
 * Explicit CLI repair for the core version written by Dataverse 3.4.6.2.
 * Deliberately not registered in install.xml or upgrade.xml.
 */
class RepairCoreVersionMigration extends Migration
{
    private const INCORRECT_VERSION = '3.4.6.2';
    private const MODE_VARIABLE = 'DATAVERSE_CORE_VERSION_REPAIR';

    public function up(): void
    {
        if (PHP_SAPI !== 'cli' || $this->_installer->isPlugin || $this->_installer->getCurrentVersion() !== null) {
            throw new RuntimeException('Run this repair separately with lib/pkp/tools/migration.php, before the normal upgrade.');
        }

        $mode = getenv(self::MODE_VARIABLE);
        $mode = $mode === false || $mode === '' ? 'check' : $mode;
        if (!in_array($mode, ['check', 'apply'], true)) {
            throw new RuntimeException(self::MODE_VARIABLE . ' must be check or apply.');
        }

        $product = Application::getName();
        $codeVersion = VersionCheck::getCurrentCodeVersion();
        if (
            !in_array($product, ['ojs2', 'ops'], true)
            || !$codeVersion
            || $codeVersion->getProductType() !== 'core'
            || $codeVersion->getProduct() !== $product
            || !preg_match('/\A3\.4\.0\.(0|[1-9][0-9]*)\z/', $codeVersion->getVersionString())
        ) {
            throw new RuntimeException('The code version must identify this OJS or OPS installation as core 3.4.0.x.');
        }
        $release = $codeVersion->getVersionString();

        $connection = DB::connection();
        $driver = $connection->getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb', 'pgsql'], true)) {
            throw new RuntimeException('Unsupported database driver for this repair.');
        }
        if ($connection->transactionLevel() !== 0) {
            throw new RuntimeException('Run this repair outside an existing transaction.');
        }
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $table = $connection->selectOne(
                'SELECT ENGINE AS engine FROM information_schema.tables WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
                [$connection->getDatabaseName(), $connection->getTablePrefix() . 'versions']
            );
            if (!$table || strcasecmp((string) $table->engine, 'InnoDB') !== 0) {
                throw new RuntimeException('The versions table must use InnoDB before this repair can run.');
            }
        }

        $result = $connection->transaction(function () use ($connection, $product, $release, $mode): string {
            $versions = $connection->table('versions')
                ->where('product_type', 'core')->where('product', $product)
                ->orderBy('version_id')->lockForUpdate()->get();
            $current = $versions->where('current', 1)->values();
            $incorrect = $versions->filter(fn ($row): bool => $this->release($row) === self::INCORRECT_VERSION)->values();

            if ($current->count() === 1 && $this->release($current->first()) === $release && $incorrect->isEmpty()) {
                return 'The core version already matches the code; no changes were made.';
            }
            if (
                $current->count() !== 1
                || $incorrect->count() !== 1
                || $current->first()->version_id != $incorrect->first()->version_id
                || $versions->contains(fn ($row): bool => !in_array((int) $row->current, [0, 1], true))
            ) {
                throw new RuntimeException('The core history does not contain exactly one active 3.4.6.2 record; refusing repair.');
            }

            $bad = $incorrect->first();
            $previous = $versions->count() >= 2 ? $versions->get($versions->count() - 2) : null;
            $matches = $versions->filter(fn ($row): bool => $this->release($row) === $release);
            if (
                $versions->last()->version_id != $bad->version_id
                || !$previous
                || $matches->count() !== 1
                || $this->release($previous) !== $release
                || (int) $previous->current !== 0
                || (string) $previous->date_installed >= (string) $bad->date_installed
                || !$this->hasCoreMetadata($bad)
                || !$this->hasCoreMetadata($previous)
            ) {
                throw new RuntimeException('The immediately preceding core record must uniquely match the code version; refusing repair.');
            }

            $plugin = $connection->table('versions')
                ->where('product_type', 'plugins.generic')->where('product', 'dataverse')
                ->where('major', 3)->where('minor', 4)->where('revision', 6)->where('build', 2)
                ->lockForUpdate()->get();
            if (
                $plugin->count() !== 1
                || (int) $bad->version_id >= PHP_INT_MAX
                || (int) $plugin->first()->version_id !== (int) $bad->version_id + 1
                || (string) $plugin->first()->date_installed !== (string) $bad->date_installed
                || $plugin->first()->product_class_name !== 'DataversePlugin'
                || (int) $plugin->first()->lazy_load !== 1
                || (int) $plugin->first()->sitewide !== 0
                || !in_array((int) $plugin->first()->current, [0, 1], true)
            ) {
                throw new RuntimeException('A matching, immediately subsequent Dataverse 3.4.6.2 installation record is required; refusing repair.');
            }

            $details = json_encode([
                'product' => $product,
                'restore' => (array) $previous,
                'remove' => (array) $bad,
            ], JSON_THROW_ON_ERROR);
            if ($mode === 'check') {
                return 'CHECK ONLY: no changes were made. Proposed repair: ' . $details;
            }

            $removed = $connection->table('versions')->where('version_id', $bad->version_id)
                ->where('product_type', 'core')->where('product', $product)->where('current', 1)->delete();
            $restored = $connection->table('versions')->where('version_id', $previous->version_id)
                ->where('product_type', 'core')->where('product', $product)->where('current', 0)->update(['current' => 1]);
            $active = $connection->table('versions')->where('product_type', 'core')
                ->where('product', $product)->where('current', 1)->get();
            if ($removed !== 1 || $restored !== 1 || $active->count() !== 1 || $active->first()->version_id != $previous->version_id) {
                throw new RuntimeException('The repaired core version failed verification; rolling back.');
            }

            return 'Core version repaired to ' . $release . ' to match the code. Original records: ' . $details;
        });

        $this->_installer->log($result);
    }

    public function down(): void
    {
        throw new RuntimeException('This repair cannot be reversed by recreating an invalid core version. Restore the maintenance backup instead.');
    }

    private function release(object $row): string
    {
        return implode('.', [(int) $row->major, (int) $row->minor, (int) $row->revision, (int) $row->build]);
    }

    private function hasCoreMetadata(object $row): bool
    {
        return $row->product_class_name === '' && (int) $row->lazy_load === 0 && (int) $row->sitewide === 1;
    }
}
