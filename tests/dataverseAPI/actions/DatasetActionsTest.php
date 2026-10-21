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
}
