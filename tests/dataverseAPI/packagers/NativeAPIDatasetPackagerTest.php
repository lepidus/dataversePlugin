<?php

use PKP\tests\PKPTestCase;
use APP\plugins\generic\dataverse\dataverseAPI\packagers\NativeAPIDatasetPackager;
use APP\plugins\generic\dataverse\classes\DataverseMetadata;
use APP\plugins\generic\dataverse\classes\entities\Dataset;
use APP\plugins\generic\dataverse\classes\entities\DatasetContact;
use APP\plugins\generic\dataverse\classes\entities\DatasetFile;

class NativeAPIDatasetPackagerTest extends PKPTestCase
{
    private $packager;
    private $license = 'CC BY 4.0';

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->packager->clear();
        parent::tearDown();
    }

    public function testNativeApiPackagerReturnsPackageDirPath(): void
    {
        $dataset = new Dataset();
        $this->packager = new NativeAPIDatasetPackager($dataset);
        $packageDirPath = $this->packager->getPackageDirPath();
        $this->assertMatchesRegularExpression('/\/tmp\/dataverse.+/', $packageDirPath);
    }

    public function testNativeApiPackagerBuildsPrimitiveMetadata(): void
    {
        $dataset = new Dataset();
        $dataset->setTitle('Test title');

        $this->packager = new NativeAPIDatasetPackager($dataset);
        $this->packager->loadPackageData();

        $titleMetadata = $this->packager->getMetadataField('title');
        $titleMetadata['value'] = $dataset->getTitle();

        $this->assertContains($titleMetadata, $this->packager->getDatasetMetadata());
    }

    public function testNativeApiPackagerBuildsSimpleCompoundMetadata(): void
    {
        $dataset = new Dataset();
        $dataset->setDescription('<p>Test description</p>');

        $this->packager = new NativeAPIDatasetPackager($dataset);
        $this->packager->loadPackageData();

        $descriptionMetadata = $this->packager->getMetadataField('description');
        $descriptionMetadata['value'] = [
            [
                'dsDescriptionValue' => [
                    'typeName' => 'dsDescriptionValue',
                    'multiple' => false,
                    'typeClass' => 'primitive',
                    'value' => $dataset->getDescription()
                ]
            ]
        ];

        $this->assertContains($descriptionMetadata, $this->packager->getDatasetMetadata());
    }

    public function testNativeApiPackagerBuildsPubCitationMetadata(): void
    {
        $dataset = new Dataset();
        $dataset->setPubCitation('User, T. (2023). <em>Test Dataset</em>. Open Preprint Systems');

        $this->packager = new NativeAPIDatasetPackager($dataset);
        $this->packager->loadPackageData();

        $publicationMetadata = $this->packager->getMetadataField('pubCitation');
        $publicationMetadata['value'] = [
            [
                'publicationCitation' => [
                    'typeName' => 'publicationCitation',
                    'multiple' => false,
                    'typeClass' => 'primitive',
                    'value' => $dataset->getPubCitation()
                ]
            ]
        ];

        $this->assertContains($publicationMetadata, $this->packager->getDatasetMetadata());
    }

    public function testNativeApiPackagerBuildsMultiCompoundMetadata(): void
    {
        $dataset = new Dataset();
        $dataset->setContact(new DatasetContact('Test name', 'test@mail.com', 'Dataverse'));

        $this->packager = new NativeAPIDatasetPackager($dataset);
        $this->packager->loadPackageData();

        $contactMetadata = $this->packager->getMetadataField('contact');
        $contactMetadata['value'] = [
            [
                'datasetContactName' => [
                    'typeName' => 'datasetContactName',
                    'multiple' => false,
                    'typeClass' => 'primitive',
                    'value' => $dataset->getContact()->getName()
                ],
                'datasetContactEmail' => [
                    'typeName' => 'datasetContactEmail',
                    'multiple' => false,
                    'typeClass' => 'primitive',
                    'value' => $dataset->getContact()->getEmail()
                ],
                'datasetContactAffiliation' => [
                    'typeName' => 'datasetContactAffiliation',
                    'multiple' => false,
                    'typeClass' => 'primitive',
                    'value' => $dataset->getContact()->getAffiliation()
                ]
            ]
        ];

        $this->assertContains($contactMetadata, $this->packager->getDatasetMetadata());
    }

    public function testNativeApiPackagerBuildsControlledVocabularyMetadata(): void
    {
        $dataset = new Dataset();
        $dataset->setSubject('Other');

        $this->packager = new NativeAPIDatasetPackager($dataset);
        $this->packager->loadPackageData();

        $subjectMetadata = $this->packager->getMetadataField('subject');
        $subjectMetadata['value'] = [$dataset->getSubject()];

        $this->assertContains($subjectMetadata, $this->packager->getDatasetMetadata());
    }

    public function testNativeApiPackagerIgnoreUndefinedMetadata(): void
    {
        $datasetFile = new DatasetFile();
        $dataset = new Dataset();
        $dataset->setFiles([$datasetFile]);

        $this->packager = new NativeAPIDatasetPackager($dataset);
        $this->packager->loadPackageData();

        $this->assertEmpty($this->packager->getDatasetMetadata());
    }

    public function testNativeApiPackagerCreatesDatasetJson(): void
    {
        $dataset = new Dataset();
        $this->packager = new NativeAPIDatasetPackager($dataset);
        $this->packager->loadPackageData();
        $this->packager->createDatasetPackage();

        $this->assertFileExists($this->packager->getPackageDirPath() . '/dataset.json');
    }

    public function testDatasetJsonContainsDatasetData(): void
    {
        $dataset = new Dataset();
        $dataset->setPersistentId('doi:10.5072/FK2/TEST');
        $dataset->setTitle('Test title');
        $dataset->setLicense($this->license);

        $this->packager = new NativeAPIDatasetPackager($dataset);
        $this->packager->loadPackageData();
        $this->packager->createDatasetPackage();

        $datasetJson = json_decode(file_get_contents($this->packager->getPackageDirPath() . '/dataset.json'), true);

        $licenseInJson = $datasetJson['license'];
        $this->assertEquals($this->license, $licenseInJson);

        $titleInJson = $datasetJson['metadataBlocks']['citation']['fields'][0]['value'];
        $this->assertEquals($dataset->getTitle(), $titleInJson);
    }
}
