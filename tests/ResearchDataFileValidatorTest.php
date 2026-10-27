<?php

use APP\plugins\generic\dataverse\classes\ResearchDataFileValidator;
use PKP\tests\PKPTestCase;

class ResearchDataFileValidatorTest extends PKPTestCase
{
    private array $paths = [];
    private ResearchDataFileValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ResearchDataFileValidator();
    }

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    public function testPlainDataFileIsResearchData(): void
    {
        $this->assertTrue($this->validator->containsResearchData($this->createFile("year,value\n2024,1\n"), 'data.csv'));
    }

    public function testReadmeFilesAreNotResearchData(): void
    {
        $readme = $this->createFile('Instructions');
        $readmeZip = $this->createZip(['README.txt' => 'Instructions']);

        $this->assertFalse($this->validator->containsResearchData($readme, 'README.txt'));
        $this->assertFalse($this->validator->containsResearchData($readme, 'Leia-me.txt'));
        $this->assertFalse($this->validator->containsResearchData($readmeZip, 'README.zip'));
        $this->assertFalse($this->validator->containsResearchData($readmeZip, 'data.zip'));
    }

    public function testZipCountsOnlyThroughANonEmptyRegularNonReadmeMember(): void
    {
        $data = $this->createZip([
            'README.txt' => 'Instructions',
            'README-folder/data.csv' => "year,value\n2024,1\n",
        ]);

        $this->assertFalse($this->validator->containsResearchData($this->createZip([]), 'empty.zip'));
        $this->assertFalse($this->validator->containsResearchData(
            $this->createZip(['folder/' => null, 'folder/README.txt' => 'Instructions']),
            'data.zip'
        ));
        $this->assertFalse($this->validator->containsResearchData($this->createZip(['data.csv' => '']), 'data.zip'));
        $this->assertTrue($this->validator->containsResearchData($data, 'README.zip'));
        $this->assertTrue($this->validator->containsResearchData($data, 'renamed.bin'));
    }

    public function testEmptyCorruptAndMissingFilesAreNotResearchData(): void
    {
        $corrupt = $this->createFile("PK\x03\x04invalid");
        $empty = $this->createFile('');

        $this->assertFalse($this->validator->containsResearchData($corrupt, 'data.zip'));
        $this->assertFalse($this->validator->containsResearchData($corrupt, 'renamed.bin'));
        $this->assertFalse($this->validator->containsResearchData($this->createFile('not a zip'), 'data.zip'));
        $this->assertFalse($this->validator->containsResearchData($empty, 'data.csv'));
        $this->assertFalse($this->validator->containsResearchData($empty . '.missing', 'data.csv'));
    }

    public function testNestedAndOpaquePackagesAreNotResearchData(): void
    {
        $nestedContents = file_get_contents($this->createZip(['data.csv' => '42']));

        $this->assertFalse($this->validator->containsResearchData($this->createZip(['nested.zip' => $nestedContents]), 'data.zip'));
        $this->assertFalse($this->validator->containsResearchData($this->createZip(['nested.bin' => $nestedContents]), 'data.zip'));
        $this->assertFalse($this->validator->containsResearchData($this->createFile(gzencode('data')), 'renamed.bin'));
        $this->assertFalse($this->validator->containsResearchData($this->createZip(['data.bin' => gzencode('data')]), 'data.zip'));
    }

    public function testKnownOpaquePackageSignaturesAndExtensionsAreRejected(): void
    {
        $opaqueContents = [
            "\x1f\x8b" . 'gzip',
            'Rar!' . ' archive',
            "7z\xbc\xaf\x27\x1c" . ' archive',
            'BZh' . ' archive',
            "\xfd7zXZ\x00" . ' archive',
            str_repeat("\0", 257) . 'ustar' . str_repeat("\0", 250),
        ];
        foreach ($opaqueContents as $contents) {
            $this->assertFalse($this->validator->containsResearchData($this->createFile($contents), 'renamed.bin'));
        }

        foreach (['data.tar', 'data.tar.gz', 'data.tgz', 'data.gz', 'data.rar', 'data.7z', 'data.bz2', 'data.xz'] as $name) {
            $this->assertFalse($this->validator->containsResearchData($this->createFile('opaque'), $name));
        }
    }

    public function testZipSymlinkIsNotResearchData(): void
    {
        $zipPath = $this->createZip(['link.csv' => 'data.csv']);
        $zip = new ZipArchive();
        $zip->open($zipPath);
        $zip->setExternalAttributesName('link.csv', ZipArchive::OPSYS_UNIX, 0120777 << 16);
        $zip->close();

        $this->assertFalse($this->validator->containsResearchData($zipPath, 'data.zip'));
    }

    private function createFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'dataverse-validator-');
        $this->paths[] = $path;
        file_put_contents($path, $contents);

        return $path;
    }

    private function createZip(array $entries): string
    {
        $path = $this->createFile('');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $name => $contents) {
            if (is_null($contents)) {
                $zip->addEmptyDir($name);
                continue;
            }
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        return $path;
    }
}
