<?php

declare(strict_types=1);

namespace APP\plugins\generic\dataverse\classes\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;
use RuntimeException;

class RepairDataverseCoreVersion extends Migration
{
    // Values of <install version> in Dataverse 3.4 tags, not plugin release numbers.
    private const AFFECTED_VERSIONS = [
        '3.4.1.0', '3.4.1.1', '3.4.2.0', '3.4.2.1', '3.4.2.2', '3.4.2.3',
        '3.4.3.0', '3.4.4.0', '3.4.4.1', '3.4.4.3', '3.4.5.1', '3.4.5.2',
        '3.4.6.0', '3.4.6.1', '3.4.6.2', '3.4.8.0',
    ];

    public function up(): void
    {
        $versionFile = 'dbscripts/xml/version.xml';
        if (!is_readable($versionFile)) {
            throw new RuntimeException('Dataverse core version repair: cannot determine the application version.');
        }
        $previousErrors = libxml_use_internal_errors(true);
        try {
            $codeVersion = simplexml_load_file($versionFile, \SimpleXMLElement::class, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
        if ($codeVersion === false) {
            throw new RuntimeException('Dataverse core version repair: cannot read the application version XML.');
        }

        $product = (string) $codeVersion->application;
        $codeRelease = (string) $codeVersion->release;
        $isSupportedProduct = in_array($product, ['ojs2', 'ops'], true);
        $isSupportedRelease = preg_match('/^3\.4\.0\.\d+$/D', $codeRelease) === 1;

        if (!$isSupportedProduct || !$isSupportedRelease || (string) $codeVersion->type !== 'core') {
            throw new RuntimeException('Dataverse core version repair supports only OJS/OPS 3.4.0.');
        }

        $repair = DB::transaction(function () use ($codeRelease, $product): ?array {
            $coreVersionQuery = DB::table('versions')
                ->where('product_type', 'core')
                ->where('product', $product);

            $coreVersions = (clone $coreVersionQuery)
                ->lockForUpdate()
                ->get();

            [$affectedVersions, $unaffectedVersions] = $coreVersions->partition(
                fn ($row) => in_array($this->versionString($row), self::AFFECTED_VERSIONS, true)
            );
            if ($affectedVersions->isEmpty()) {
                return null;
            }

            $currentVersions = $coreVersions->filter(fn ($row) => (int) $row->current === 1);
            if ($currentVersions->count() !== 1) {
                throw new RuntimeException(
                    'Dataverse core version repair: expected one current version.'
                );
            }

            $hasUnknownNewerVersion = $unaffectedVersions->contains(
                fn ($row) => version_compare($this->versionString($row), $codeRelease, '>')
            );
            if ($hasUnknownNewerVersion) {
                throw new RuntimeException('Dataverse core version repair: history contains an unknown newer version.');
            }

            $versionToRestore = $unaffectedVersions->sort(
                fn ($left, $right) => version_compare($this->versionString($right), $this->versionString($left))
            )->first();
            if (
                !$versionToRestore
                || preg_match('/^3\.4\.0\.\d+$/D', $this->versionString($versionToRestore)) !== 1
            ) {
                throw new RuntimeException('Dataverse core version repair: expected one identifiable real 3.4.0 version.');
            }
            $currentVersion = $currentVersions->first();
            $currentMatchesCode = $this->versionString($currentVersion) === $codeRelease;
            $currentIsAffected = $affectedVersions->contains($currentVersion);
            if (!$currentMatchesCode && !$currentIsAffected) {
                throw new RuntimeException('Dataverse core version repair: current version does not match the files.');
            }

            // Preserve the original installation date and metadata; never invent a core upgrade.
            (clone $coreVersionQuery)->where((array) $versionToRestore)->update(['current' => 1]);
            foreach ($affectedVersions as $affectedVersion) {
                (clone $coreVersionQuery)->where((array) $affectedVersion)->delete();
            }
            return [
                'product' => $product,
                'codeVersion' => $codeRelease,
                'restored' => (array) $versionToRestore,
                'removed' => $affectedVersions->values()->all(),
            ];
        });

        if ($repair !== null) {
            error_log('Dataverse core version repair: ' . json_encode($repair, JSON_THROW_ON_ERROR));
            if ($this->versionString((object) $repair['restored']) !== $codeRelease) {
                error_log('Dataverse core version repair: run php tools/upgrade.php upgrade from the application root '
                    . 'to execute the core upgrade steps skipped while the recorded version was incorrect.');
            }
        }
    }

    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }

    private function versionString(object $row): string
    {
        return implode('.', [
            (int) $row->major,
            (int) $row->minor,
            (int) $row->revision,
            (int) $row->build,
        ]);
    }
}
