<?php

use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;
use APP\plugins\generic\dataverse\classes\entities\DataverseResponse;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DataverseCollectionActions;
use GuzzleHttp\Client;
use PKP\tests\PKPTestCase;

class DataverseCollectionActionsTest extends PKPTestCase
{
    public function testFlattenedFieldsHaveUniqueNames(): void
    {
        $configuration = new DataverseConfiguration();
        $configuration->setDataverseUrl('https://test.dataverse.org/dataverses/testDataverse');
        $configuration->setAPIToken('apiToken');
        $actions = new DataverseCollectionActions($configuration, new Client());

        $publicationCitation = [
            'name' => 'publicationCitation',
            'displayName' => 'Related Publication Citation',
        ];
        $distributionDate = [
            'name' => 'distributionDate',
            'displayName' => 'Distribution Date',
        ];
        $metadataBlocks = [
            'citation' => [
                'fields' => [
                    [
                        'name' => 'publication',
                        'childFields' => [$publicationCitation],
                    ],
                    $distributionDate,
                ],
            ],
            'custom' => [
                'fields' => [
                    [
                        'name' => 'relatedPublication',
                        'childFields' => [$publicationCitation, $distributionDate],
                    ],
                ],
            ],
        ];

        $flattenedFields = $actions->getFlattenedFields($metadataBlocks);

        $this->assertSame(
            [$publicationCitation, $distributionDate],
            $flattenedFields
        );
    }

    public function testRequiredMetadataExcludesPublicationRelationType(): void
    {
        $configuration = new DataverseConfiguration();
        $configuration->setDataverseUrl('https://test.dataverse.org/dataverses/testDataverse');
        $configuration->setAPIToken('apiToken');
        $responseBody = json_encode([
            'data' => [[
                'name' => 'citation',
                'displayName' => 'Citation Metadata',
                'fields' => [
                    [
                        'name' => 'publicationRelationType',
                        'isRequired' => true,
                    ],
                    [
                        'name' => 'customRequiredField',
                        'isRequired' => true,
                    ],
                ],
            ]],
        ]);
        $response = new DataverseResponse(200, 'OK', $responseBody);
        $cache = new class () {
            public function getContents()
            {
                return null;
            }

            public function getCacheTime(): int
            {
                return 0;
            }

            public function flush(): void
            {
            }

            public function setEntireCache(array $contents): void
            {
            }
        };
        $cacheManager = new class ($cache) {
            private $cache;

            public function __construct($cache)
            {
                $this->cache = $cache;
            }

            public function getFileCache(...$args)
            {
                return $this->cache;
            }
        };
        $actions = new class ($configuration, new Client(), $cacheManager, $response) extends DataverseCollectionActions {
            private $metadataResponse;

            public function __construct($configuration, $client, $cacheManager, $metadataResponse)
            {
                parent::__construct($configuration, $client);
                $this->cacheManager = $cacheManager;
                $this->metadataResponse = $metadataResponse;
            }

            public function nativeAPIRequest(
                string $method,
                string $uri,
                array $options = [],
                bool $returnResponse = true
            ): ?DataverseResponse {
                return $this->metadataResponse;
            }
        };

        $requiredMetadata = $actions->getRequiredMetadata();
        $flattenedFields = $actions->getFlattenedFields($requiredMetadata);

        $this->assertSame(
            ['customRequiredField'],
            array_column($flattenedFields, 'name')
        );
    }
}
