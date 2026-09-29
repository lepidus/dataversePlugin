<?php

/**
 * Checks whether an uploaded file contains a non-empty research data candidate.
 * This is a structural check; it cannot determine the scientific meaning of a file.
 */
class ResearchDataFileValidator
{
    private const MAX_ZIP_ENTRIES = 10000;

    public function containsResearchData(string $path, string $name): bool
    {
        $size = @filesize($path);
        if (!is_file($path) || !is_readable($path) || $size === false || $size === 0) {
            return false;
        }

        $stream = @fopen($path, 'rb');
        if ($stream === false) {
            return false;
        }
        $header = @fread($stream, 512);
        fclose($stream);
        if ($header === false || $header === '') {
            return false;
        }

        if ($this->isZipSignature($header) || $this->hasZipExtension($name)) {
            if (!$this->isZipSignature($header)) {
                return false;
            }
            return $this->zipContainsResearchData($path);
        }

        return !$this->isReadmeName($name) && !$this->isOpaqueArchive($header, $name);
    }

    private function zipContainsResearchData(string $path): bool
    {
        if (!class_exists('ZipArchive')) {
            return false;
        }

        $zip = new ZipArchive();
        if (@$zip->open($path, ZipArchive::CHECKCONS) !== true) {
            return false;
        }

        try {
            if ($zip->numFiles === 0 || $zip->numFiles > self::MAX_ZIP_ENTRIES) {
                return false;
            }

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = @$zip->statIndex($index);
                if ($entry === false || !isset($entry['name'], $entry['size'])) {
                    return false;
                }

                $name = $entry['name'];
                if (
                    substr($name, -1) === '/'
                    || $entry['size'] === 0
                    || $this->isReadmeName($name)
                    || !$this->isRegularZipEntry($zip, $index)
                ) {
                    continue;
                }

                $header = @$zip->getFromIndex($index, 512);

                // A nested package is not evidence of data until its contents are checked.
                // Reject it conservatively instead of extracting untrusted archive content.
                if (
                    $header !== false
                    && $header !== ''
                    && !$this->isZipSignature($header)
                    && !$this->hasZipExtension($name)
                    && !$this->isOpaqueArchive($header, $name)
                ) {
                    return true;
                }
            }

            return false;
        } finally {
            $zip->close();
        }
    }

    private function isRegularZipEntry(ZipArchive $zip, int $index): bool
    {
        $operations = 0;
        $attributes = 0;
        if (!$zip->getExternalAttributesIndex($index, $operations, $attributes)) {
            return false;
        }
        if ($operations !== ZipArchive::OPSYS_UNIX) {
            return true;
        }

        $type = ($attributes >> 16) & 0170000;
        return $type === 0 || $type === 0100000;
    }

    private function isZipSignature(string $header): bool
    {
        return in_array(substr($header, 0, 4), ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"], true);
    }

    private function isOpaqueArchive(string $header, string $name): bool
    {
        $lowerName = strtolower($name);
        foreach (['.tar', '.tar.gz', '.tgz', '.gz', '.rar', '.7z', '.bz2', '.xz'] as $extension) {
            if (substr($lowerName, -strlen($extension)) === $extension) {
                return true;
            }
        }

        return substr($header, 257, 5) === 'ustar'
            || substr($header, 0, 2) === "\x1f\x8b"
            || substr($header, 0, 4) === 'Rar!'
            || substr($header, 0, 6) === "7z\xbc\xaf\x27\x1c"
            || substr($header, 0, 3) === 'BZh'
            || substr($header, 0, 6) === "\xfd7zXZ\x00";
    }

    private function hasZipExtension(string $name): bool
    {
        return strtolower(substr($name, -4)) === '.zip';
    }

    private function isReadmeName(string $name): bool
    {
        $name = strtolower(basename(str_replace('\\', '/', $name)));
        foreach (['readme', 'leiame', 'leia-me', 'leame'] as $keyword) {
            if (strpos($name, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }
}
