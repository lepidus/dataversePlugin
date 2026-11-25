<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.dataverse.dataverseAPI.actions.DatasetActions');
import('plugins.generic.dataverse.classes.dataverseConfiguration.DataverseConfiguration');

class DatasetActionsTest extends PKPTestCase
{
    private const PERSISTENT_ID = 'doi:10.12345/FK2/ABCDEFG';

    private $requests = [];

    private function createActions(array $responses): DatasetActions
    {
        $configuration = new DataverseConfiguration();
        $configuration->setDataverseUrl('https://test.dataverse.org/dataverse/testDataverse');
        $configuration->setAPIToken('apiToken');
        $this->requests = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->requests));

        return new DatasetActions($configuration, new Client(['handler' => $stack]));
    }

    private function ownersResponse(?array $isPartOf): Response
    {
        return new Response(200, [], json_encode(['status' => 'OK', 'data' => ['id' => 1, 'isPartOf' => $isPartOf]]));
    }

    private function datasetResponseWithoutOwners(int $datasetId): Response
    {
        return new Response(200, [], json_encode(['status' => 'OK', 'data' => ['id' => $datasetId]]));
    }

    private function contentsResponse(array $items): Response
    {
        return new Response(200, [], json_encode(['status' => 'OK', 'data' => $items]));
    }

    private function formatCitation(string $citation, string $persistentUrl): string
    {
        $method = new ReflectionMethod(DatasetActions::class, 'formatCitation');
        $method->setAccessible(true);

        return $method->invoke($this->createActions([]), $citation, $persistentUrl);
    }

    public function testCitationLinksOnlyHttpPersistentUrls(): void
    {
        $persistentUrl = 'javascript:alert(document.domain)';

        $citation = $this->formatCitation('Author, 2026, "Data", ' . $persistentUrl . ', Demo Dataverse, V1', $persistentUrl);

        $this->assertSame('Author, 2026, &quot;Data&quot;, ' . $persistentUrl . ', Demo Dataverse, V1', $citation);
    }

    public function testCitationLinksHttpsPersistentUrl(): void
    {
        $persistentUrl = 'https://doi.org/10.12345/FK2/ABCDEFG';

        $citation = $this->formatCitation('Author, 2026, "Data", ' . $persistentUrl . ', Demo Dataverse, V1', $persistentUrl);

        $this->assertSame(
            'Author, 2026, &quot;Data&quot;, <a href="' . $persistentUrl . '">' . $persistentUrl . '</a>, Demo Dataverse, V1',
            $citation
        );
    }

    public function testDatasetOwnedByTheConfiguredCollectionIsInTheCollection(): void
    {
        $actions = $this->createActions([
            $this->ownersResponse(['type' => 'DATAVERSE', 'identifier' => 'testDataverse', 'isPartOf' => ['type' => 'DATAVERSE', 'identifier' => 'root']]),
        ]);

        $this->assertSame(self::PERSISTENT_ID, $actions->findInCurrentCollection(self::PERSISTENT_ID));
        $this->assertSame(
            'https://test.dataverse.org/api/datasets/:persistentId?persistentId=doi%3A10.12345%2FFK2%2FABCDEFG&returnOwners=true',
            (string) $this->requests[0]['request']->getUri()
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

        $this->assertSame(self::PERSISTENT_ID, $actions->findInCurrentCollection(self::PERSISTENT_ID));
    }

    public function testDatasetOfAnotherCollectionIsNotInTheCollection(): void
    {
        $actions = $this->createActions([
            $this->ownersResponse(['type' => 'DATAVERSE', 'identifier' => 'otherDataverse', 'isPartOf' => ['type' => 'DATAVERSE', 'identifier' => 'root']]),
        ]);

        $this->assertNull($actions->findInCurrentCollection(self::PERSISTENT_ID));
    }

    public function testDatasetWithoutOwnersIsNotInTheCollection(): void
    {
        $actions = $this->createActions([$this->ownersResponse(null)]);

        $this->assertNull($actions->findInCurrentCollection(self::PERSISTENT_ID));
    }

    public function testDatasetInASubcollectionIsFoundThroughTheCollectionContentsWhenOwnersAreNotReturned(): void
    {
        $actions = $this->createActions([
            $this->datasetResponseWithoutOwners(77),
            $this->contentsResponse([['type' => 'dataset', 'id' => 1], ['type' => 'dataverse', 'id' => 5]]),
            $this->contentsResponse([['type' => 'dataset', 'id' => 77]]),
        ]);

        $this->assertSame(self::PERSISTENT_ID, $actions->findInCurrentCollection(self::PERSISTENT_ID));
        $this->assertSame('https://test.dataverse.org/api/dataverses/5/contents', (string) end($this->requests)['request']->getUri());
    }

    public function testDatasetMissingFromTheCollectionContentsIsNotInTheCollection(): void
    {
        $actions = $this->createActions([
            $this->datasetResponseWithoutOwners(77),
            $this->contentsResponse([['type' => 'dataset', 'id' => 1], ['type' => 'dataverse', 'id' => 5]]),
            $this->contentsResponse([['type' => 'dataset', 'id' => 2]]),
        ]);

        $this->assertNull($actions->findInCurrentCollection(self::PERSISTENT_ID));
    }

    public function testDatasetRequestedInAnotherCaseIsFoundWithThePersistentIdStoredByDataverse(): void
    {
        $actions = $this->createActions([
            new Response(200, [], json_encode(['status' => 'OK', 'data' => [
                'id' => 1,
                'protocol' => 'doi',
                'authority' => '10.12345',
                'separator' => '/',
                'identifier' => 'fk2.AbCdEfG',
                'isPartOf' => ['type' => 'DATAVERSE', 'identifier' => 'testDataverse'],
            ]])),
        ]);

        $this->assertSame('doi:10.12345/fk2.AbCdEfG', $actions->findInCurrentCollection('doi:10.12345/FK2.ABCDEFG'));
    }
}
