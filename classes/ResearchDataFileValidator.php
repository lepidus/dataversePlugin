<?php

namespace APP\plugins\generic\dataverse\classes;

use ZipArchive;

class ResearchDataFileValidator
{
    private const MAX_ZIP_ENTRIES = 10000;
    private const HEADER_LENGTH = 512;
    private const README_NAME_PATTERN = '/(^|[^a-z])(readme|leiame|leia-me|leame)/';
    private const ZIP_BASED_DATA_EXTENSIONS = ['xlsx', 'xlsm', 'xltx', 'docx', 'pptx', 'ods', 'odt', 'odp', 'odg'];
    private const OPAQUE_ARCHIVE_EXTENSIONS = ['.tar', '.tar.gz', '.tgz', '.gz', '.rar', '.7z', '.bz2', '.xz'];

    public static function isReadmeName(string $name): bool
    {
        $baseName = strtolower(basename(str_replace('\\', '/', $name)));

        return (bool) preg_match(self::README_NAME_PATTERN, $baseName);
    }

    public function containsResearchData(string $path, string $name): bool
    {
        $header = $this->readHeader($path);
        if ($header === null) {
            return false;
        }

        if ($this->hasZipBasedDataExtension($name)) {
            return !self::isReadmeName($name);
        }

        if ($this->isZipSignature($header)) {
            return $this->zipContainsResearchData($path);
        }

        return !self::isReadmeName($name)
            && !$this->hasZipExtension($name)
            && !$this->isOpaqueArchive($header, $name);
    }

    private function readHeader(string $path): ?string
    {
        if (!is_file($path) || !is_readable($path) || !@filesize($path)) {
            return null;
        }

        $stream = @fopen($path, 'rb');
        if ($stream === false) {
            return null;
        }

        $header = @fread($stream, self::HEADER_LENGTH);
        fclose($stream);

        return $header ?: null;
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
            $inspectedEntries = min($zip->numFiles, self::MAX_ZIP_ENTRIES);
            for ($index = 0; $index < $inspectedEntries; $index++) {
                if ($this->zipEntryIsResearchData($zip, $index)) {
                    return true;
                }
            }

            return false;
        } finally {
            $zip->close();
        }
    }

    private function zipEntryIsResearchData(ZipArchive $zip, int $index): bool
    {
        $entry = @$zip->statIndex($index);
        if ($entry === false || !isset($entry['name'], $entry['size'])) {
            return false;
        }

        $name = $entry['name'];
        if (
            str_ends_with($name, '/')
            || $entry['size'] === 0
            || self::isReadmeName($name)
            || !$this->isRegularZipEntry($zip, $index)
        ) {
            return false;
        }

        if ($this->hasZipBasedDataExtension($name)) {
            return true;
        }

        $header = @$zip->getFromIndex($index, self::HEADER_LENGTH);

        return $header !== false
            && $header !== ''
            && !$this->isZipSignature($header)
            && !$this->hasZipExtension($name)
            && !$this->isOpaqueArchive($header, $name);
    }

    private function isRegularZipEntry(ZipArchive $zip, int $index): bool
    {
        $operatingSystem = 0;
        $attributes = 0;
        if (!$zip->getExternalAttributesIndex($index, $operatingSystem, $attributes)) {
            return false;
        }
        if ($operatingSystem !== ZipArchive::OPSYS_UNIX) {
            return true;
        }

        $fileType = ($attributes >> 16) & 0170000;
        return $fileType === 0 || $fileType === 0100000;
    }

    private function isZipSignature(string $header): bool
    {
        return in_array(substr($header, 0, 4), ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"], true);
    }

    private function isOpaqueArchive(string $header, string $name): bool
    {
        $lowerName = strtolower($name);
        foreach (self::OPAQUE_ARCHIVE_EXTENSIONS as $extension) {
            if (str_ends_with($lowerName, $extension)) {
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
        return strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'zip';
    }

    private function hasZipBasedDataExtension(string $name): bool
    {
        return in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::ZIP_BASED_DATA_EXTENSIONS, true);
    }
}
