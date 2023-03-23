<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.classes.dataverseAPI.packagers.NativeAPIDatasetPackager');

class NativeAPIDatasetPackagerTest extends PKPTestCase
{
    private $packager;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->packager->clear();
        parent::tearDown();
    }

    public function testNativeAPIPackagerReturnsPackageDirPath(): void
    {
        $dataset = new Dataset();
        $this->packager = new NativeAPIDatasetPackager($dataset);
        $packageDirPath = $this->packager->getPackageDirPath();
        $this->assertMatchesRegularExpression('/\/tmp\/dataverse.+/', $packageDirPath);
    }

    public function testNativeAPIPackagerBuildsPrimitiveMetadata(): void
    {
        $dataset = new Dataset();
        $dataset->setTitle('Test title');

        $this->packager = new NativeAPIDatasetPackager($dataset);
        $this->packager->loadMetadata();

        $titleMetadata = $this->packager->getMetadataField('title');
        $titleMetadata['value'] = $dataset->getTitle();

        $this->assertContains($titleMetadata, $this->packager->getDatasetMetadata());
    }

    public function testNativeAPIPackagerBuildsSimpleCompoundMetadata(): void
    {
        $dataset = new Dataset();
        $dataset->setDescription('<p>Test description</p>');

        $this->packager = new NativeAPIDatasetPackager($dataset);
        $this->packager->loadMetadata();

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

    public function testNativeAPIPackagerBuildsPubCitationMetadata(): void
    {
        $dataset = new Dataset();
        $dataset->setPubCitation('User, T. (2023). <em>Test Dataset</em>. Open Preprint Systems');

        $this->packager = new NativeAPIDatasetPackager($dataset);
        $this->packager->loadMetadata();

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

    public function testNativeAPIPackagerBuildsMultiCompoundMetadata(): void
    {
        $dataset = new Dataset();
        $dataset->setContact(new DatasetContact('Test name', 'test@mail.com', 'Dataverse'));

        $this->packager = new NativeAPIDatasetPackager($dataset);
        $this->packager->loadMetadata();

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

    public function testNativeAPIPackagerBuildsControlledVocabularyMetadata(): void
    {
        $dataset = new Dataset();
        $dataset->setSubject('Other');

        $this->packager = new NativeAPIDatasetPackager($dataset);
        $this->packager->loadMetadata();

        $subjectMetadata = $this->packager->getMetadataField('subject');
        $subjectMetadata['value'] = [$dataset->getSubject()];

        $this->assertContains($subjectMetadata, $this->packager->getDatasetMetadata());
    }

    public function testNativeAPIPackagerIgnoreUndefinedMetadata(): void
    {
        $datasetFile = new DatasetFile();
        $dataset = new Dataset();
        $dataset->setFiles([$datasetFile]);

        $this->packager = new NativeAPIDatasetPackager($dataset);
        $this->packager->loadMetadata();

        $this->assertEmpty($this->packager->getDatasetMetadata());
    }

    public function testNativeAPIPackagerCreatesDatasetJson(): void
    {
        $dataset = new Dataset();
        $this->packager = new NativeAPIDatasetPackager($dataset);
        $this->packager->loadMetadata();
        $this->packager->createDatasetPackage();

        $this->assertFileExists($this->packager->getPackageDirPath() . '/dataset.json');
    }

    public function testDatasetJsonContainsDatasetData(): void
    {
        $dataset = new Dataset();
        $dataset->setPersistentId('doi:10.5072/FK2/TEST');
        $dataset->setTitle('Test title');

        $this->packager = new NativeAPIDatasetPackager($dataset);
        $this->packager->loadMetadata();
        $this->packager->createDatasetPackage();

        $datasetJson = json_decode(file_get_contents($this->packager->getPackageDirPath() . '/dataset.json'), true);

        $this->assertEquals($dataset->getTitle(), $datasetJson['fields'][0]['value']);
    }
}
