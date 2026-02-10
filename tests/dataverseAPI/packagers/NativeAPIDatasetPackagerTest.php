<?php

use PKP\tests\PKPTestCase;
use APP\plugins\generic\dataverse\dataverseAPI\packagers\NativeAPIDatasetPackager;
use APP\plugins\generic\dataverse\dataverseAPI\DataverseClient;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DataverseCollectionActions;
use APP\plugins\generic\dataverse\classes\DataverseMetadata;
use APP\plugins\generic\dataverse\classes\entities\Dataset;
use APP\plugins\generic\dataverse\classes\entities\DatasetContact;
use APP\plugins\generic\dataverse\classes\entities\DatasetFile;
use APP\plugins\generic\dataverse\classes\entities\DatasetRelatedPublication;

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

    private function createMockDataverseClient(): DataverseClient
    {
        $mockCollectionActions = Mockery::mock(DataverseCollectionActions::class);
        $mockCollectionActions->shouldReceive('getRequiredMetadata')->andReturn([]);

        $mockClient = Mockery::mock(DataverseClient::class);
        $mockClient->shouldReceive('getDataverseCollectionActions')->andReturn($mockCollectionActions);

        return $mockClient;
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

    public function testNativeApiPackagerBuildsRelatedPublicationMetadata(): void
    {
        $relatedPublication = new DatasetRelatedPublication(
            'User, T. (2023). <em>Test Dataset</em>. Open Preprint Systems',
            'doi',
            '10.1234/LepidusPreprints.1245',
            'https://doi.org/10.1234/LepidusPreprints.1245'
        );
        $dataset = new Dataset();
        $dataset->setRelatedPublication($relatedPublication);

        $this->packager = new NativeAPIDatasetPackager($dataset);
        $this->packager->loadPackageData();

        $publicationMetadata = $this->packager->getMetadataField('relatedPublication');
        $publicationMetadata['value'] = [
            [
                'publicationCitation' => [
                    'typeName' => 'publicationCitation',
                    'multiple' => false,
                    'typeClass' => 'primitive',
                    'value' => $relatedPublication->getCitation()
                ],
                'publicationIDType' => [
                    'typeName' => 'publicationIDType',
                    'multiple' => false,
                    'typeClass' => 'controlledVocabulary',
                    'value' => $relatedPublication->getIdType()
                ],
                'publicationIDNumber' => [
                    'typeName' => 'publicationIDNumber',
                    'multiple' => false,
                    'typeClass' => 'primitive',
                    'value' => $relatedPublication->getIdNumber()
                ],
                'publicationURL' => [
                    'typeName' => 'publicationURL',
                    'multiple' => false,
                    'typeClass' => 'primitive',
                    'value' => $relatedPublication->getUrl()
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
        $mockClient = $this->createMockDataverseClient();
        $this->packager = new NativeAPIDatasetPackager($dataset, $mockClient);
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

        $mockClient = $this->createMockDataverseClient();
        $this->packager = new NativeAPIDatasetPackager($dataset, $mockClient);
        $this->packager->loadPackageData();
        $this->packager->createDatasetPackage();

        $datasetJson = json_decode(file_get_contents($this->packager->getPackageDirPath() . '/dataset.json'), true);

        $licenseInJson = $datasetJson['license'];
        $this->assertEquals($this->license, $licenseInJson);

        $titleInJson = $datasetJson['metadataBlocks']['citation']['fields'][0]['value'];
        $this->assertEquals($dataset->getTitle(), $titleInJson);
    }

    private function createMockDataverseClientWithMetadata(array $requiredMetadata): DataverseClient
    {
        $mockCollectionActions = Mockery::mock(DataverseCollectionActions::class);
        $mockCollectionActions->shouldReceive('getRequiredMetadata')->andReturn($requiredMetadata);

        $mockClient = Mockery::mock(DataverseClient::class);
        $mockClient->shouldReceive('getDataverseCollectionActions')->andReturn($mockCollectionActions);

        return $mockClient;
    }

    private function getDatasetJsonContent(): array
    {
        return json_decode(
            file_get_contents($this->packager->getPackagePath()),
            true
        );
    }

    public function testAdditionalMetadataEmptyRequiredMetadata(): void
    {
        $dataset = new Dataset();
        $dataset->setPersistentId('doi:10.5072/FK2/TEST');
        $dataset->setTitle('Test title');

        $mockClient = $this->createMockDataverseClientWithMetadata([]);
        $this->packager = new NativeAPIDatasetPackager($dataset, $mockClient);
        $this->packager->createDatasetPackage();

        $json = $this->getDatasetJsonContent();

        $this->assertArrayHasKey('citation', $json['metadataBlocks']);
        $this->assertCount(1, $json['metadataBlocks']);
    }

    public function testAdditionalMetadataInitializesNewBlock(): void
    {
        $dataset = new Dataset();
        $dataset->setPersistentId('doi:10.5072/FK2/TEST');
        $dataset->setTitle('Test title');

        $requiredMetadata = [
            'customBlock' => [
                'name' => 'customBlock',
                'displayName' => 'Custom Metadata Block',
                'fields' => []
            ]
        ];

        $mockClient = $this->createMockDataverseClientWithMetadata($requiredMetadata);
        $this->packager = new NativeAPIDatasetPackager($dataset, $mockClient);
        $this->packager->createDatasetPackage();

        $json = $this->getDatasetJsonContent();

        $this->assertArrayHasKey('customBlock', $json['metadataBlocks']);
        $this->assertEquals('Custom Metadata Block', $json['metadataBlocks']['customBlock']['displayName']);
        $this->assertEmpty($json['metadataBlocks']['customBlock']['fields']);
    }

    public function testAdditionalMetadataDoesNotReinitializeExistingBlock(): void
    {
        $dataset = new Dataset();
        $dataset->setPersistentId('doi:10.5072/FK2/TEST');
        $dataset->setTitle('Test title');

        $requiredMetadata = [
            'citation' => [
                'name' => 'citation',
                'displayName' => 'Citation Metadata',
                'fields' => []
            ]
        ];

        $mockClient = $this->createMockDataverseClientWithMetadata($requiredMetadata);
        $this->packager = new NativeAPIDatasetPackager($dataset, $mockClient);
        $this->packager->createDatasetPackage();

        $json = $this->getDatasetJsonContent();

        $this->assertNotEmpty($json['metadataBlocks']['citation']['fields']);
        $titleField = $json['metadataBlocks']['citation']['fields'][0];
        $this->assertEquals('title', $titleField['typeName']);
        $this->assertEquals('Test title', $titleField['value']);
    }

    public function testAdditionalMetadataAddsSimpleField(): void
    {
        $dataset = new Dataset();
        $dataset->setPersistentId('doi:10.5072/FK2/TEST');
        $dataset->setTitle('Test title');
        $dataset->setData('customField', 'custom value');

        $requiredMetadata = [
            'customBlock' => [
                'name' => 'customBlock',
                'displayName' => 'Custom Block',
                'fields' => [
                    [
                        'name' => 'customField',
                        'multiple' => false,
                        'typeClass' => 'primitive',
                        'isRequired' => true
                    ]
                ]
            ]
        ];

        $mockClient = $this->createMockDataverseClientWithMetadata($requiredMetadata);
        $this->packager = new NativeAPIDatasetPackager($dataset, $mockClient);
        $this->packager->createDatasetPackage();

        $json = $this->getDatasetJsonContent();

        $customBlockFields = $json['metadataBlocks']['customBlock']['fields'];
        $this->assertCount(1, $customBlockFields);
        $this->assertEquals('customField', $customBlockFields[0]['typeName']);
        $this->assertFalse($customBlockFields[0]['multiple']);
        $this->assertEquals('primitive', $customBlockFields[0]['typeClass']);
        $this->assertEquals('custom value', $customBlockFields[0]['value']);
    }

    public function testAdditionalMetadataIgnoresSimpleFieldNotInDataset(): void
    {
        $dataset = new Dataset();
        $dataset->setPersistentId('doi:10.5072/FK2/TEST');
        $dataset->setTitle('Test title');

        $requiredMetadata = [
            'customBlock' => [
                'name' => 'customBlock',
                'displayName' => 'Custom Block',
                'fields' => [
                    [
                        'name' => 'missingField',
                        'multiple' => false,
                        'typeClass' => 'primitive',
                        'isRequired' => true
                    ]
                ]
            ]
        ];

        $mockClient = $this->createMockDataverseClientWithMetadata($requiredMetadata);
        $this->packager = new NativeAPIDatasetPackager($dataset, $mockClient);
        $this->packager->createDatasetPackage();

        $json = $this->getDatasetJsonContent();

        $this->assertEmpty($json['metadataBlocks']['customBlock']['fields']);
    }

    public function testAdditionalMetadataAddsControlledVocabularyField(): void
    {
        $dataset = new Dataset();
        $dataset->setPersistentId('doi:10.5072/FK2/TEST');
        $dataset->setTitle('Test title');
        $dataset->setData('vocabField', 'Vocabulary Term');

        $requiredMetadata = [
            'customBlock' => [
                'name' => 'customBlock',
                'displayName' => 'Custom Block',
                'fields' => [
                    [
                        'name' => 'vocabField',
                        'multiple' => true,
                        'typeClass' => 'controlledVocabulary',
                        'isRequired' => true
                    ]
                ]
            ]
        ];

        $mockClient = $this->createMockDataverseClientWithMetadata($requiredMetadata);
        $this->packager = new NativeAPIDatasetPackager($dataset, $mockClient);
        $this->packager->createDatasetPackage();

        $json = $this->getDatasetJsonContent();

        $customBlockFields = $json['metadataBlocks']['customBlock']['fields'];
        $this->assertCount(1, $customBlockFields);
        $this->assertEquals('vocabField', $customBlockFields[0]['typeName']);
        $this->assertTrue($customBlockFields[0]['multiple']);
        $this->assertEquals('controlledVocabulary', $customBlockFields[0]['typeClass']);
        $this->assertEquals('Vocabulary Term', $customBlockFields[0]['value']);
    }

    public function testAdditionalMetadataCreatesNewCompoundFieldWithMultipleTrue(): void
    {
        $dataset = new Dataset();
        $dataset->setPersistentId('doi:10.5072/FK2/TEST');
        $dataset->setTitle('Test title');
        $dataset->setData('childA', 'value A');
        $dataset->setData('childB', 'value B');

        $requiredMetadata = [
            'customBlock' => [
                'name' => 'customBlock',
                'displayName' => 'Custom Block',
                'fields' => [
                    [
                        'name' => 'compoundField',
                        'multiple' => true,
                        'typeClass' => 'compound',
                        'isRequired' => true,
                        'childFields' => [
                            ['name' => 'childA', 'multiple' => false, 'typeClass' => 'primitive', 'isRequired' => true],
                            ['name' => 'childB', 'multiple' => false, 'typeClass' => 'primitive', 'isRequired' => false]
                        ]
                    ]
                ]
            ]
        ];

        $mockClient = $this->createMockDataverseClientWithMetadata($requiredMetadata);
        $this->packager = new NativeAPIDatasetPackager($dataset, $mockClient);
        $this->packager->createDatasetPackage();

        $json = $this->getDatasetJsonContent();

        $customBlockFields = $json['metadataBlocks']['customBlock']['fields'];
        $this->assertCount(1, $customBlockFields);

        $compoundField = $customBlockFields[0];
        $this->assertEquals('compoundField', $compoundField['typeName']);
        $this->assertTrue($compoundField['multiple']);
        $this->assertEquals('compound', $compoundField['typeClass']);
        $this->assertCount(1, $compoundField['value']);
        $innerValue = $compoundField['value'][0];

        $this->assertArrayHasKey('childA', $innerValue);
        $this->assertEquals('childA', $innerValue['childA']['typeName']);
        $this->assertFalse($innerValue['childA']['multiple']);
        $this->assertEquals('primitive', $innerValue['childA']['typeClass']);
        $this->assertEquals('value A', $innerValue['childA']['value']);

        $this->assertArrayHasKey('childB', $innerValue);
        $this->assertEquals('value B', $innerValue['childB']['value']);
    }

    public function testAdditionalMetadataCreatesNewCompoundFieldWithMultipleFalse(): void
    {
        $dataset = new Dataset();
        $dataset->setPersistentId('doi:10.5072/FK2/TEST');
        $dataset->setTitle('Test title');
        $dataset->setData('singleChild', 'single value');

        $requiredMetadata = [
            'customBlock' => [
                'name' => 'customBlock',
                'displayName' => 'Custom Block',
                'fields' => [
                    [
                        'name' => 'singleCompound',
                        'multiple' => false,
                        'typeClass' => 'compound',
                        'isRequired' => true,
                        'childFields' => [
                            ['name' => 'singleChild', 'multiple' => false, 'typeClass' => 'primitive', 'isRequired' => true]
                        ]
                    ]
                ]
            ]
        ];

        $mockClient = $this->createMockDataverseClientWithMetadata($requiredMetadata);
        $this->packager = new NativeAPIDatasetPackager($dataset, $mockClient);
        $this->packager->createDatasetPackage();

        $json = $this->getDatasetJsonContent();

        $compoundField = $json['metadataBlocks']['customBlock']['fields'][0];
        $this->assertEquals('singleCompound', $compoundField['typeName']);
        $this->assertFalse($compoundField['multiple']);

        $this->assertArrayHasKey('singleChild', $compoundField['value']);
        $this->assertEquals('single value', $compoundField['value']['singleChild']['value']);
    }

    public function testAdditionalMetadataUpdatesExistingCompoundFieldWithMultipleTrue(): void
    {
        $dataset = new Dataset();
        $dataset->setPersistentId('doi:10.5072/FK2/TEST');
        $dataset->setTitle('Test title');
        $dataset->setDescription('Test description');
        $dataset->setData('extraDescChild', 'extra value');

        $requiredMetadata = [
            'citation' => [
                'name' => 'citation',
                'displayName' => 'Citation Metadata',
                'fields' => [
                    [
                        'name' => 'dsDescription',
                        'multiple' => true,
                        'typeClass' => 'compound',
                        'isRequired' => true,
                        'childFields' => [
                            ['name' => 'extraDescChild', 'multiple' => false, 'typeClass' => 'primitive', 'isRequired' => false]
                        ]
                    ]
                ]
            ]
        ];

        $mockClient = $this->createMockDataverseClientWithMetadata($requiredMetadata);
        $this->packager = new NativeAPIDatasetPackager($dataset, $mockClient);
        $this->packager->createDatasetPackage();

        $json = $this->getDatasetJsonContent();

        $citationFields = $json['metadataBlocks']['citation']['fields'];
        $dsDescriptionField = null;
        foreach ($citationFields as $field) {
            if ($field['typeName'] === 'dsDescription') {
                $dsDescriptionField = $field;
                break;
            }
        }

        $this->assertNotNull($dsDescriptionField);
        $firstEntry = $dsDescriptionField['value'][0];

        $this->assertArrayHasKey('dsDescriptionValue', $firstEntry);
        $this->assertEquals('Test description', $firstEntry['dsDescriptionValue']['value']);

        $this->assertArrayHasKey('extraDescChild', $firstEntry);
        $this->assertEquals('extra value', $firstEntry['extraDescChild']['value']);
    }

    public function testAdditionalMetadataUpdatesExistingCompoundFieldWithMultipleFalse(): void
    {
        $dataset = new Dataset();
        $dataset->setPersistentId('doi:10.5072/FK2/TEST');
        $dataset->setTitle('Test title');
        $dataset->setDepositor('Test Depositor');
        $dataset->setData('depositorExtra', 'extra depositor data');
        $dataset->setData('childX', 'value X');
        $dataset->setData('childY', 'value Y');

        $requiredMetadata = [
            'customBlock' => [
                'name' => 'customBlock',
                'displayName' => 'Custom Block',
                'fields' => [
                    [
                        'name' => 'myCompound',
                        'multiple' => false,
                        'typeClass' => 'compound',
                        'isRequired' => true,
                        'childFields' => [
                            ['name' => 'childX', 'multiple' => false, 'typeClass' => 'primitive', 'isRequired' => true]
                        ]
                    ],
                    [
                        'name' => 'myCompound',
                        'multiple' => false,
                        'typeClass' => 'compound',
                        'isRequired' => true,
                        'childFields' => [
                            ['name' => 'childY', 'multiple' => false, 'typeClass' => 'primitive', 'isRequired' => false]
                        ]
                    ]
                ]
            ]
        ];

        $mockClient = $this->createMockDataverseClientWithMetadata($requiredMetadata);
        $this->packager = new NativeAPIDatasetPackager($dataset, $mockClient);
        $this->packager->createDatasetPackage();

        $json = $this->getDatasetJsonContent();

        $customFields = $json['metadataBlocks']['customBlock']['fields'];
        $this->assertCount(1, $customFields);

        $compoundField = $customFields[0];
        $this->assertEquals('myCompound', $compoundField['typeName']);
        $this->assertFalse($compoundField['multiple']);
        $this->assertArrayHasKey('childX', $compoundField['value']);
        $this->assertEquals('value X', $compoundField['value']['childX']['value']);
        $this->assertArrayHasKey('childY', $compoundField['value']);
        $this->assertEquals('value Y', $compoundField['value']['childY']['value']);
    }

    public function testAdditionalMetadataIgnoresChildFieldsNotInDataset(): void
    {
        $dataset = new Dataset();
        $dataset->setPersistentId('doi:10.5072/FK2/TEST');
        $dataset->setTitle('Test title');
        $dataset->setData('presentChild', 'present value');

        $requiredMetadata = [
            'customBlock' => [
                'name' => 'customBlock',
                'displayName' => 'Custom Block',
                'fields' => [
                    [
                        'name' => 'partialCompound',
                        'multiple' => true,
                        'typeClass' => 'compound',
                        'isRequired' => true,
                        'childFields' => [
                            ['name' => 'presentChild', 'multiple' => false, 'typeClass' => 'primitive', 'isRequired' => true],
                            ['name' => 'absentChild', 'multiple' => false, 'typeClass' => 'primitive', 'isRequired' => false]
                        ]
                    ]
                ]
            ]
        ];

        $mockClient = $this->createMockDataverseClientWithMetadata($requiredMetadata);
        $this->packager = new NativeAPIDatasetPackager($dataset, $mockClient);
        $this->packager->createDatasetPackage();

        $json = $this->getDatasetJsonContent();

        $compoundField = $json['metadataBlocks']['customBlock']['fields'][0];
        $innerValue = $compoundField['value'][0];

        $this->assertArrayHasKey('presentChild', $innerValue);
        $this->assertEquals('present value', $innerValue['presentChild']['value']);
        $this->assertArrayNotHasKey('absentChild', $innerValue);
    }

    public function testAdditionalMetadataProcessesMultipleBlocks(): void
    {
        $dataset = new Dataset();
        $dataset->setPersistentId('doi:10.5072/FK2/TEST');
        $dataset->setTitle('Test title');
        $dataset->setData('fieldBlockA', 'value A');
        $dataset->setData('fieldBlockB', 'value B');

        $requiredMetadata = [
            'blockA' => [
                'name' => 'blockA',
                'displayName' => 'Block A',
                'fields' => [
                    [
                        'name' => 'fieldBlockA',
                        'multiple' => false,
                        'typeClass' => 'primitive',
                        'isRequired' => true
                    ]
                ]
            ],
            'blockB' => [
                'name' => 'blockB',
                'displayName' => 'Block B',
                'fields' => [
                    [
                        'name' => 'fieldBlockB',
                        'multiple' => true,
                        'typeClass' => 'controlledVocabulary',
                        'isRequired' => true
                    ]
                ]
            ]
        ];

        $mockClient = $this->createMockDataverseClientWithMetadata($requiredMetadata);
        $this->packager = new NativeAPIDatasetPackager($dataset, $mockClient);
        $this->packager->createDatasetPackage();

        $json = $this->getDatasetJsonContent();

        $this->assertArrayHasKey('blockA', $json['metadataBlocks']);
        $this->assertArrayHasKey('blockB', $json['metadataBlocks']);
        $this->assertArrayHasKey('citation', $json['metadataBlocks']);

        $blockAFields = $json['metadataBlocks']['blockA']['fields'];
        $this->assertCount(1, $blockAFields);
        $this->assertEquals('fieldBlockA', $blockAFields[0]['typeName']);
        $this->assertEquals('value A', $blockAFields[0]['value']);

        $blockBFields = $json['metadataBlocks']['blockB']['fields'];
        $this->assertCount(1, $blockBFields);
        $this->assertEquals('fieldBlockB', $blockBFields[0]['typeName']);
        $this->assertEquals('value B', $blockBFields[0]['value']);
    }

    public function testAdditionalMetadataCompoundFieldWithAllChildFieldsMissing(): void
    {
        $dataset = new Dataset();
        $dataset->setPersistentId('doi:10.5072/FK2/TEST');
        $dataset->setTitle('Test title');

        $requiredMetadata = [
            'customBlock' => [
                'name' => 'customBlock',
                'displayName' => 'Custom Block',
                'fields' => [
                    [
                        'name' => 'emptyCompound',
                        'multiple' => true,
                        'typeClass' => 'compound',
                        'isRequired' => true,
                        'childFields' => [
                            ['name' => 'childX', 'multiple' => false, 'typeClass' => 'primitive', 'isRequired' => true],
                            ['name' => 'childY', 'multiple' => false, 'typeClass' => 'primitive', 'isRequired' => false]
                        ]
                    ]
                ]
            ]
        ];

        $mockClient = $this->createMockDataverseClientWithMetadata($requiredMetadata);
        $this->packager = new NativeAPIDatasetPackager($dataset, $mockClient);
        $this->packager->createDatasetPackage();

        $json = $this->getDatasetJsonContent();

        $compoundField = $json['metadataBlocks']['customBlock']['fields'][0];
        $this->assertEquals('emptyCompound', $compoundField['typeName']);

        $this->assertCount(1, $compoundField['value']);
        $this->assertEmpty($compoundField['value'][0]);
    }

    public function testAdditionalMetadataFieldAddedToCitationBlock(): void
    {
        $dataset = new Dataset();
        $dataset->setPersistentId('doi:10.5072/FK2/TEST');
        $dataset->setTitle('Test title');
        $dataset->setData('customCitationField', 'citation extra');

        $requiredMetadata = [
            'citation' => [
                'name' => 'citation',
                'displayName' => 'Citation Metadata',
                'fields' => [
                    [
                        'name' => 'customCitationField',
                        'multiple' => false,
                        'typeClass' => 'primitive',
                        'isRequired' => true
                    ]
                ]
            ]
        ];

        $mockClient = $this->createMockDataverseClientWithMetadata($requiredMetadata);
        $this->packager = new NativeAPIDatasetPackager($dataset, $mockClient);
        $this->packager->createDatasetPackage();

        $json = $this->getDatasetJsonContent();

        $citationFields = $json['metadataBlocks']['citation']['fields'];

        $this->assertEquals('title', $citationFields[0]['typeName']);

        $customField = null;
        foreach ($citationFields as $field) {
            if ($field['typeName'] === 'customCitationField') {
                $customField = $field;
                break;
            }
        }
        $this->assertNotNull($customField);
        $this->assertEquals('citation extra', $customField['value']);
    }
}
