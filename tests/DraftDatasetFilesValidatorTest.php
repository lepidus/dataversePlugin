<?php

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.file.TemporaryFile');
import('lib.pkp.classes.file.TemporaryFileDAO');
import('plugins.generic.dataverse.classes.draftDatasetFile.DraftDatasetFile');
import('plugins.generic.dataverse.classes.DraftDatasetFilesValidator');
import('plugins.generic.dataverse.classes.ResearchDataFileValidator');

class DraftDatasetFilesValidatorTest extends PKPTestCase
{
    /** @var array<int, string> */
    private $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    public function testReadmeTextAndReadmeZipContainingOnlyReadmeAreRejected(): void
    {
        $validator = new ResearchDataFileValidator();
        $readme = $this->createFile('Instructions');
        $zip = $this->createZip(['README.txt' => 'Instructions']);

        $this->assertFalse($validator->containsResearchData($readme, 'README.txt'));
        $this->assertFalse($validator->containsResearchData($zip, 'README.zip'));
        $this->assertFalse($validator->containsResearchData($zip, 'data.zip'));
    }

    public function testZipMustContainANonemptyRegularNonReadmeEntry(): void
    {
        $validator = new ResearchDataFileValidator();
        $empty = $this->createZip([]);
        $directories = $this->createZip(['folder/' => null, 'folder/README.txt' => 'Instructions']);
        $data = $this->createZip([
            'README.txt' => 'Instructions',
            'README-folder/data.csv' => "year,value\n2024,1\n",
        ]);
        $emptyData = $this->createZip(['data.csv' => '']);

        $this->assertFalse($validator->containsResearchData($empty, 'empty.zip'));
        $this->assertFalse($validator->containsResearchData($directories, 'data.zip'));
        $this->assertFalse($validator->containsResearchData($emptyData, 'data.zip'));
        $this->assertTrue($validator->containsResearchData($data, 'README.zip'));
        $this->assertTrue($validator->containsResearchData($data, 'renamed.bin'));
    }

    public function testCorruptAndMissingPackagesAreRejected(): void
    {
        $validator = new ResearchDataFileValidator();
        $corrupt = $this->createFile("PK\x03\x04invalid");
        $empty = $this->createFile('');

        $this->assertFalse($validator->containsResearchData($corrupt, 'data.zip'));
        $this->assertFalse($validator->containsResearchData($corrupt, 'renamed.bin'));
        $this->assertFalse($validator->containsResearchData($empty, 'data.csv'));
        $this->assertFalse($validator->containsResearchData($empty . '.missing', 'data.csv'));
    }

    public function testOpaqueAndNestedPackagesDoNotCountAsData(): void
    {
        $validator = new ResearchDataFileValidator();
        $nestedZip = $this->createZip(['data.csv' => '42']);
        $nestedContents = file_get_contents($nestedZip);
        $outerZip = $this->createZip(['nested.zip' => $nestedContents]);
        $renamedNestedZip = $this->createZip(['nested.bin' => $nestedContents]);
        $gzip = $this->createFile(gzencode('README only'));
        $gzipInZip = $this->createZip(['data.bin' => gzencode('README only')]);

        $this->assertFalse($validator->containsResearchData($outerZip, 'data.zip'));
        $this->assertFalse($validator->containsResearchData($renamedNestedZip, 'data.zip'));
        $this->assertFalse($validator->containsResearchData($gzip, 'renamed.bin'));
        $this->assertFalse($validator->containsResearchData($gzipInZip, 'data.zip'));
        $this->assertFalse($validator->containsResearchData($this->createFile('not a zip'), 'data.zip'));
    }

    public function testKnownOpaquePackageSignaturesAndExtensionsAreRejected(): void
    {
        $validator = new ResearchDataFileValidator();
        $tarHeader = str_repeat("\0", 257) . 'ustar' . str_repeat("\0", 250);
        foreach ([
            "\x1f\x8b" . 'gzip',
            'Rar!' . ' archive',
            "7z\xbc\xaf\x27\x1c" . ' archive',
            'BZh' . ' archive',
            "\xfd7zXZ\x00" . ' archive',
            $tarHeader,
        ] as $contents) {
            $this->assertFalse($validator->containsResearchData($this->createFile($contents), 'renamed.bin'));
        }
        foreach (['data.tar', 'data.gz', 'data.rar', 'data.7z', 'data.bz2', 'data.xz'] as $name) {
            $this->assertFalse($validator->containsResearchData($this->createFile('opaque'), $name));
        }
    }

    public function testZipSymlinkDoesNotCountAsData(): void
    {
        $validator = new ResearchDataFileValidator();
        $zipPath = $this->createZip(['link.csv' => 'data.csv']);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath) === true);
        $this->assertTrue($zip->setExternalAttributesName('link.csv', ZipArchive::OPSYS_UNIX, 0120777 << 16));
        $zip->close();

        $this->assertFalse($validator->containsResearchData($zipPath, 'data.zip'));
    }

    public function testDatasetValidatorUsesOwnedTemporaryFilePathAndPreservesMissingDraft(): void
    {
        $readmePath = $this->createFile('Instructions');
        $readmeZipPath = $this->createZip(['README.txt' => 'Instructions']);
        $dataZipPath = $this->createZip(['README.txt' => 'Instructions', 'data.csv' => '42']);
        $paths = [11 => $readmePath, 12 => $readmeZipPath, 13 => $dataZipPath];

        $dao = $this->getMockBuilder(TemporaryFileDAO::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getTemporaryFile'])
            ->getMock();
        $dao->method('getTemporaryFile')->willReturnCallback(function ($fileId, $userId) use ($paths) {
            $this->assertSame(7, $userId);
            if (!isset($paths[$fileId])) {
                return null;
            }
            $file = $this->getMockBuilder(TemporaryFile::class)
                ->onlyMethods(['getFilePath'])
                ->getMock();
            $file->method('getFilePath')->willReturn($paths[$fileId]);
            $file->setData('filetype', $fileId === 11 ? 'text/plain' : 'application/zip');
            return $file;
        });

        $previous = DAORegistry::registerDAO('TemporaryFileDAO', $dao);
        try {
            $validator = new DraftDatasetFilesValidator();
            $readme = $this->draft('README.txt', 11);
            $readmeZip = $this->draft('README.zip', 12);
            $dataZip = $this->draft('README.zip', 13);
            $missing = $this->draft('data.csv', 99);

            $this->assertTrue($validator->datasetHasReadmeFile([$readme]));
            $this->assertFalse($validator->datasetHasNonReadmeFile([$readme, $readmeZip]));
            $this->assertTrue($validator->datasetHasNonReadmeFile([$readme, $dataZip]));
            $this->assertFalse($validator->datasetHasNonReadmeFile([$missing]));
            $this->assertFalse($validator->datasetHasReadmeFile([$missing]));
        } finally {
            $daos = & DAORegistry::getDAOs();
            if ($previous === null) {
                unset($daos['TemporaryFileDAO']);
            } else {
                $daos['TemporaryFileDAO'] = $previous;
            }
        }
    }

    private function draft(string $name, int $fileId): DraftDatasetFile
    {
        $file = new DraftDatasetFile();
        $file->setFileName($name);
        $file->setData('fileId', $fileId);
        $file->setData('userId', 7);
        return $file;
    }

    private function createFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'dataverse-validator-');
        $this->paths[] = $path;
        file_put_contents($path, $contents);
        return $path;
    }

    /** @param array<string, string|null> $entries */
    private function createZip(array $entries): string
    {
        $path = $this->createFile('');
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create ZIP fixture');
        }
        foreach ($entries as $name => $contents) {
            if ($contents === null) {
                $zip->addEmptyDir($name);
            } else {
                $zip->addFromString($name, $contents);
            }
        }
        $zip->close();
        return $path;
    }
}
