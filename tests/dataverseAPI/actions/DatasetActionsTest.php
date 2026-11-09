<?php

use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;
use APP\plugins\generic\dataverse\dataverseAPI\actions\DatasetActions;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PKP\tests\PKPTestCase;

class DatasetActionsTest extends PKPTestCase
{
    public function testDatasetLocksAreRequestedByPersistentId(): void
    {
        $configuration = new DataverseConfiguration();
        $configuration->setDataverseUrl('https://test.dataverse.org/dataverses/testDataverse');
        $configuration->setAPIToken('apiToken');
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode(['status' => 'OK', 'data' => [['lockType' => 'InReview']]])),
        ]));
        $stack->push(Middleware::history($history));
        $actions = new DatasetActions($configuration, new Client(['handler' => $stack]));

        $locks = $actions->getDatasetLocks('doi:10.5072/FK2/TEST');

        $this->assertSame([['lockType' => 'InReview']], $locks);
        $this->assertSame(
            'https://test.dataverse.org/api/datasets/:persistentId/locks?persistentId=doi%3A10.5072%2FFK2%2FTEST',
            (string) $history[0]['request']->getUri()
        );
    }

    private array $collectionRequests = [];

    private function createActions(array $responses): DatasetActions
    {
        $configuration = new DataverseConfiguration();
        $configuration->setDataverseUrl('https://test.dataverse.org/dataverse/testDataverse');
        $configuration->setAPIToken('apiToken');
        $this->collectionRequests = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->collectionRequests));

        return new DatasetActions($configuration, new Client(['handler' => $stack]));
    }

    private function ownersResponse(?array $isPartOf): Response
    {
        return new Response(200, [], json_encode(['status' => 'OK', 'data' => ['id' => 1, 'isPartOf' => $isPartOf]]));
    }

    public function testDatasetOwnedByTheConfiguredCollectionIsInTheCollection(): void
    {
        $actions = $this->createActions([
            $this->ownersResponse(['type' => 'DATAVERSE', 'identifier' => 'testDataverse', 'isPartOf' => ['type' => 'DATAVERSE', 'identifier' => 'root']]),
        ]);

        $this->assertTrue($actions->isInCurrentCollection('doi:10.12345/FK2/ABCDEFG'));
        $this->assertSame(
            'https://test.dataverse.org/api/datasets/:persistentId?persistentId=doi%3A10.12345%2FFK2%2FABCDEFG&returnOwners=true',
            (string) $this->collectionRequests[0]['request']->getUri()
        );
    }

    public function testDatasetOfASubcollectionIsInTheCollection(): void
    {
        $actions = $this->createActions([
            $this->ownersResponse([
                'type' => 'DATAVERSE',
                'identifier' => 'subcollection',
                'isPartOf' => ['type' => 'DATAVERSE', 'identifier' => 'testDataverse', 'isPartOf' => ['type' => 'DATAVERSE', 'identifier' => 'root']],
            ]),
        ]);

        $this->assertTrue($actions->isInCurrentCollection('doi:10.12345/FK2/ABCDEFG'));
    }

    public function testDatasetOfAnotherCollectionIsNotInTheCollection(): void
    {
        $actions = $this->createActions([
            $this->ownersResponse(['type' => 'DATAVERSE', 'identifier' => 'otherDataverse', 'isPartOf' => ['type' => 'DATAVERSE', 'identifier' => 'root']]),
        ]);

        $this->assertFalse($actions->isInCurrentCollection('doi:10.12345/FK2/ABCDEFG'));
    }

    public function testDatasetWithoutOwnersIsNotInTheCollection(): void
    {
        $actions = $this->createActions([$this->ownersResponse(null)]);

        $this->assertFalse($actions->isInCurrentCollection('doi:10.12345/FK2/ABCDEFG'));
    }

    private function datasetResponseWithoutOwners(int $datasetId): Response
    {
        return new Response(200, [], json_encode(['status' => 'OK', 'data' => ['id' => $datasetId]]));
    }

    private function contentsResponse(array $items): Response
    {
        return new Response(200, [], json_encode(['status' => 'OK', 'data' => $items]));
    }

    public function testDatasetInASubcollectionIsFoundThroughTheCollectionContentsWhenOwnersAreNotReturned(): void
    {
        $actions = $this->createActions([
            $this->datasetResponseWithoutOwners(77),
            $this->contentsResponse([['type' => 'dataset', 'id' => 1], ['type' => 'dataverse', 'id' => 5]]),
            $this->contentsResponse([['type' => 'dataset', 'id' => 77]]),
        ]);

        $this->assertTrue($actions->isInCurrentCollection('doi:10.12345/FK2/ABCDEFG'));
        $this->assertSame('https://test.dataverse.org/api/dataverses/5/contents', (string) end($this->collectionRequests)['request']->getUri());
    }

    public function testDatasetMissingFromTheCollectionContentsIsNotInTheCollection(): void
    {
        $actions = $this->createActions([
            $this->datasetResponseWithoutOwners(77),
            $this->contentsResponse([['type' => 'dataset', 'id' => 1], ['type' => 'dataverse', 'id' => 5]]),
            $this->contentsResponse([['type' => 'dataset', 'id' => 2]]),
        ]);

        $this->assertFalse($actions->isInCurrentCollection('doi:10.12345/FK2/ABCDEFG'));
    }
}
