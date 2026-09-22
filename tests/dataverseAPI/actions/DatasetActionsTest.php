<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PKP\tests\PKPTestCase;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;
use APP\plugins\generic\dataverse\classes\entities\Dataset;
use APP\plugins\generic\dataverse\classes\entities\DatasetContact;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DatasetActions;

class DatasetActionsTest extends PKPTestCase
{
    private MockHandler $mockHandler;

    private function createActions(array $responses): DatasetActions
    {
        $configuration = new DataverseConfiguration();
        $configuration->setDataverseUrl('https://test.dataverse.org/dataverses/testDataverse');
        $configuration->setAPIToken('apiToken');

        $this->mockHandler = new MockHandler($responses);
        $client = new Client(['handler' => HandlerStack::create($this->mockHandler)]);

        return new DatasetActions($configuration, $client);
    }

    private function createTestDataset(): Dataset
    {
        $dataset = new Dataset();
        $dataset->setTitle('Research data');
        $dataset->setDescription('Research data description');
        $dataset->setSubject('Other');
        $dataset->setKeywords([]);
        $dataset->setAuthors([]);
        $dataset->setContact(new DatasetContact('Author', 'author@example.org', null));

        return $dataset;
    }

    private function requiredMetadataResponse(): Response
    {
        return new Response(200, [], json_encode(['data' => []]));
    }

    public function testGetSendsRequiredMetadataRequestThroughGivenClient(): void
    {
        $actions = $this->createActions([
            new Response(200, [], file_get_contents(__DIR__ . '/../../fixtures/datasetVersionsResponseExample.json')),
            $this->requiredMetadataResponse(),
        ]);

        $actions->get('doi:10.12345/FK2/ABCDEFG');

        $this->assertEquals(0, $this->mockHandler->count());
    }

    public function testCreateSendsRequiredMetadataRequestThroughGivenClient(): void
    {
        $actions = $this->createActions([
            $this->requiredMetadataResponse(),
            new Response(201, [], json_encode(['data' => ['persistentId' => 'doi:10.12345/FK2/ABCDEFG']])),
        ]);

        $identifier = $actions->create($this->createTestDataset());

        $this->assertEquals('doi:10.12345/FK2/ABCDEFG', $identifier->getPersistentId());
        $this->assertEquals(0, $this->mockHandler->count());
    }

    public function testUpdateSendsRequiredMetadataRequestThroughGivenClient(): void
    {
        $actions = $this->createActions([
            $this->requiredMetadataResponse(),
            new Response(200, [], json_encode(['data' => []])),
        ]);

        $dataset = $this->createTestDataset();
        $dataset->setPersistentId('doi:10.12345/FK2/ABCDEFG');
        $actions->update($dataset);

        $this->assertEquals(0, $this->mockHandler->count());
    }
}
