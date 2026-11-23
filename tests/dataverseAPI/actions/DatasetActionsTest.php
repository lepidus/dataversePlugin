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

    private const DATASET_IDENTIFIERS = ['protocol' => 'doi', 'authority' => '10.12345', 'identifier' => 'FK2/ABCDEFG'];

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

    public function testPublishReturnsTheStatusReceivedFromDataverse(): void
    {
        $actions = $this->createActions([new Response(202, [], '{"status":"OK"}')]);

        $this->assertSame(202, $actions->publish('doi:10.5072/FK2/TEST'));
        $this->assertSame('POST', $this->collectionRequests[0]['request']->getMethod());
    }

    private function ownersResponse(?array $isPartOf): Response
    {
        return new Response(200, [], json_encode(['status' => 'OK', 'data' => self::DATASET_IDENTIFIERS + ['id' => 1, 'isPartOf' => $isPartOf]]));
    }

    public function testDatasetOwnedByTheConfiguredCollectionIsInTheCollection(): void
    {
        $actions = $this->createActions([
            $this->ownersResponse(['type' => 'DATAVERSE', 'identifier' => 'testDataverse', 'isPartOf' => ['type' => 'DATAVERSE', 'identifier' => 'root']]),
        ]);

        $this->assertSame('doi:10.12345/FK2/ABCDEFG', $actions->findInCurrentCollection('doi:10.12345/fk2/abcdefg'));
        $this->assertSame(
            'https://test.dataverse.org/api/datasets/:persistentId?persistentId=doi%3A10.12345%2Ffk2%2Fabcdefg&returnOwners=true',
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

        $this->assertSame('doi:10.12345/FK2/ABCDEFG', $actions->findInCurrentCollection('doi:10.12345/fk2/abcdefg'));
    }

    public function testDatasetOfAnotherCollectionIsNotInTheCollection(): void
    {
        $actions = $this->createActions([
            $this->ownersResponse(['type' => 'DATAVERSE', 'identifier' => 'otherDataverse', 'isPartOf' => ['type' => 'DATAVERSE', 'identifier' => 'root']]),
        ]);

        $this->assertNull($actions->findInCurrentCollection('doi:10.12345/FK2/ABCDEFG'));
    }

    public function testDatasetWithoutOwnersIsNotInTheCollection(): void
    {
        $actions = $this->createActions([$this->ownersResponse(null)]);

        $this->assertNull($actions->findInCurrentCollection('doi:10.12345/FK2/ABCDEFG'));
    }

    private function datasetResponseWithoutOwners(int $datasetId): Response
    {
        return new Response(200, [], json_encode(['status' => 'OK', 'data' => self::DATASET_IDENTIFIERS + ['id' => $datasetId]]));
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

        $this->assertSame('doi:10.12345/FK2/ABCDEFG', $actions->findInCurrentCollection('doi:10.12345/fk2/abcdefg'));
        $this->assertSame('https://test.dataverse.org/api/dataverses/5/contents', (string) end($this->collectionRequests)['request']->getUri());
    }

    public function testDatasetMissingFromTheCollectionContentsIsNotInTheCollection(): void
    {
        $actions = $this->createActions([
            $this->datasetResponseWithoutOwners(77),
            $this->contentsResponse([['type' => 'dataset', 'id' => 1], ['type' => 'dataverse', 'id' => 5]]),
            $this->contentsResponse([['type' => 'dataset', 'id' => 2]]),
        ]);

        $this->assertNull($actions->findInCurrentCollection('doi:10.12345/FK2/ABCDEFG'));
    }
}
