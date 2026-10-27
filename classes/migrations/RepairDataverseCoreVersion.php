<?php

declare(strict_types=1);

namespace APP\plugins\generic\dataverse\classes\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;
use PKP\site\VersionCheck;
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
        $codeVersion = VersionCheck::getCurrentCodeVersion();
        if (!$codeVersion) {
            throw new RuntimeException('Dataverse core version repair: cannot determine the application version.');
        }

        $product = $codeVersion->getProduct();
        $codeRelease = $codeVersion->getVersionString(false);
        $isSupportedProduct = in_array($product, ['ojs2', 'ops'], true);
        $isSupportedRelease = preg_match('/^3\.4\.0\.\d+$/D', $codeRelease) === 1;

        if (!$isSupportedProduct || !$isSupportedRelease || $codeVersion->getProductType() !== 'core') {
            throw new RuntimeException('Dataverse core version repair supports only OJS/OPS 3.4.0.');
        }

        DB::transaction(function () use ($codeRelease, $product): void {
            $coreVersionQuery = DB::table('versions')
                ->where('product_type', 'core')
                ->where('product', $product);

            $coreVersions = (clone $coreVersionQuery)
                ->lockForUpdate()
                ->get(['major', 'minor', 'revision', 'build', 'current']);

            [$affectedVersions, $unaffectedVersions] = $coreVersions->partition(
                fn ($row) => in_array($this->versionString($row), self::AFFECTED_VERSIONS, true)
            );
            if ($affectedVersions->isEmpty()) {
                return;
            }

            $matchingVersions = $coreVersions->filter(fn ($row) => $this->versionString($row) === $codeRelease);
            $currentVersions = $coreVersions->filter(fn ($row) => (int) $row->current === 1);
            if ($matchingVersions->count() !== 1 || $currentVersions->count() !== 1) {
                throw new RuntimeException(
                    'Dataverse core version repair: expected one matching and one current version.'
                );
            }

            $hasUnknownNewerVersion = $unaffectedVersions->contains(
                fn ($row) => version_compare($this->versionString($row), $codeRelease, '>')
            );
            if ($hasUnknownNewerVersion) {
                throw new RuntimeException('Dataverse core version repair: history contains an unknown newer version.');
            }

            $versionToRestore = $matchingVersions->first();
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
        });
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
