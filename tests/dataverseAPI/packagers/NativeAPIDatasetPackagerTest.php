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

    public function testNativeAPIPackagerReturnsPackageDirPath(): void
    {
        $dataset = new Dataset();
        $packager = new NativeAPIDatasetPackager($dataset);
        $packageDirPath = $packager->getPackageDirPath();
        $this->assertMatchesRegularExpression('/\/tmp\/dataverse.+/', $packageDirPath);
    }

    public function testNativeAPIPackagerBuildsPrimitiveMetadata(): void
    {
        $dataset = new Dataset();
        $dataset->setTitle('Test title');

        $packager = new NativeAPIDatasetPackager($dataset);
        $packager->loadMetadata();

        $titleMetadata = $packager->getMetadataField('title');
        $titleMetadata['value'] = $dataset->getTitle();

        $this->assertContains($titleMetadata, $packager->getDatasetMetadata());
    }

    public function testNativeAPIPackagerBuildsSimpleCompoundMetadata(): void
    {
        $dataset = new Dataset();
        $dataset->setDescription('<p>Test description</p>');

        $packager = new NativeAPIDatasetPackager($dataset);
        $packager->loadMetadata();

        $descriptionMetadata = $packager->getMetadataField('description');
        $descriptionMetadata['value'] = [
            [
                'typeName' => 'dsDescriptionValue',
                'multiple' => false,
                'typeClass' => 'primitive',
                'value' => $dataset->getDescription()
            ]
        ];

        $this->assertContains($descriptionMetadata, $packager->getDatasetMetadata());
    }

    public function testNativeAPIPackagerBuildsPubCitationMetadata(): void
    {
        $dataset = new Dataset();
        $dataset->setPubCitation('User, T. (2023). <em>Test Dataset</em>. Open Preprint Systems');

        $packager = new NativeAPIDatasetPackager($dataset);
        $packager->loadMetadata();

        $publicationMetadata = $packager->getMetadataField('pubCitation');
        $publicationMetadata['value'] = [
            [
                'typeName' => 'publicationCitation',
                'multiple' => false,
                'typeClass' => 'primitive',
                'value' => $dataset->getPubCitation()
            ]
        ];

        $this->assertContains($publicationMetadata, $packager->getDatasetMetadata());
    }

    public function testNativeAPIPackagerBuildsMultiCompoundMetadata(): void
    {
        $dataset = new Dataset();
        $dataset->setContact(new DatasetContact('Test name', 'test@mail.com', 'Dataverse'));

        $packager = new NativeAPIDatasetPackager($dataset);
        $packager->loadMetadata();

        $contactMetadata = $packager->getMetadataField('contact');
        $contactMetadata['value'] = [
            [
                'typeName' => 'datasetContactName',
                'multiple' => false,
                'typeClass' => 'primitive',
                'value' => $dataset->getContact()->getName()
            ],
            [
                'typeName' => 'datasetContactEmail',
                'multiple' => false,
                'typeClass' => 'primitive',
                'value' => $dataset->getContact()->getEmail()
            ],
            [
                'typeName' => 'datasetContactAffiliation',
                'multiple' => false,
                'typeClass' => 'primitive',
                'value' => $dataset->getContact()->getAffiliation()
            ],
        ];

        $this->assertContains($contactMetadata, $packager->getDatasetMetadata());
    }

    public function testNativeAPIPackagerBuildsControlledVocabularyMetadata(): void
    {
        $dataset = new Dataset();
        $dataset->setSubject('Other');

        $packager = new NativeAPIDatasetPackager($dataset);
        $packager->loadMetadata();

        $subjectMetadata = $packager->getMetadataField('subject');
        $subjectMetadata['value'] = [$dataset->getSubject()];

        $this->assertContains($subjectMetadata, $packager->getDatasetMetadata());
    }

    public function testNativeAPIPackagerIgnoreUndefinedMetadata(): void
    {
        $datasetFile = new DatasetFile();
        $dataset = new Dataset();
        $dataset->setFiles([$datasetFile]);

        $packager = new NativeAPIDatasetPackager($dataset);
        $packager->loadMetadata();

        $this->assertEmpty($packager->getDatasetMetadata());
    }

    public function testNativeAPIPackagerCreatesDatasetJson(): void
    {
        $dataset = new Dataset();
        $packager = new NativeAPIDatasetPackager($dataset);
        $packager->loadMetadata();
        $packager->createDatasetPackage();

        $this->assertFileExists($packager->getPackageDirPath() . '/dataset.json');
    }

    public function testDatasetJsonContainsDatasetData(): void
    {
        $dataset = new Dataset();
        $dataset->setTitle('Test title');

        $packager = new NativeAPIDatasetPackager($dataset);
        $packager->loadMetadata();
        $packager->createDatasetPackage();

        $datasetJson = json_decode(file_get_contents($packager->getPackageDirPath() . '/dataset.json'), true);

        $this->assertEquals($dataset->getTitle(), $datasetJson['datasetVersion']['metadataBlocks']['citation']['fields'][0]['value']);
    }
}
