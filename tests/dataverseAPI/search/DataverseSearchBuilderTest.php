<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;

import('plugins.generic.dataverse.dataverseAPI.search.DataverseSearchBuilder');
import('plugins.generic.dataverse.classes.dataverseConfiguration.DataverseConfiguration');

class DataverseSearchBuilderTest extends PHPUnit\Framework\TestCase
{
    private const DATAVERSE_URL = 'https://test.dataverse.org/dataverse/testDataverse';
    private const SEARCH_URL = 'https://test.dataverse.org/api/search?';

    private function getDataverseSearchBuilder(?Client $httpClient = null): DataverseSearchBuilder
    {
        $configuration = new DataverseConfiguration();
        $httpClient = $httpClient ?? new Client();

        $configuration->setDataverseUrl(self::DATAVERSE_URL);
        $configuration->setAPIToken('testToken');

        return new DataverseSearchBuilder($configuration, $httpClient);
    }

    public function testEmptyQuery(): void
    {
        $searchBuilder = $this->getDataverseSearchBuilder();
        $this->assertEquals(
            [self::SEARCH_URL . 'q=*'],
            $searchBuilder->getSearchUrls()
        );
    }

    public function testSingleQuery(): void
    {
        $searchBuilder = $this->getDataverseSearchBuilder();
        $searchBuilder->addQuery('test');
        $this->assertEquals(
            [self::SEARCH_URL . 'q=test'],
            $searchBuilder->getSearchUrls()
        );
    }

    public function testMultipleQueries(): void
    {
        $searchBuilder = $this->getDataverseSearchBuilder()
            ->addQuery('title:test')
            ->addQuery('language:English');

        $this->assertEquals(
            [self::SEARCH_URL . 'q=title:test+language:English'],
            $searchBuilder->getSearchUrls()
        );
    }

    public function testSingleType(): void
    {
        $searchBuilder = $this->getDataverseSearchBuilder();
        $searchBuilder->addType('dataset');
        $this->assertEquals(
            [self::SEARCH_URL . 'q=*&type=dataset'],
            $searchBuilder->getSearchUrls()
        );
    }

    public function testMultipleTypes(): void
    {
        $searchBuilder = $this->getDataverseSearchBuilder()
            ->addType('dataset')
            ->addType('file');

        $this->assertEquals(
            [self::SEARCH_URL . 'q=*&type=dataset&type=file'],
            $searchBuilder->getSearchUrls()
        );
    }

    public function testSingleFilterQuery(): void
    {
        $searchBuilder = $this->getDataverseSearchBuilder();
        $searchBuilder->addFilterQuery('publicationDate', '2016');
        $this->assertEquals(
            [self::SEARCH_URL . 'q=*&fq=publicationDate:2016'],
            $searchBuilder->getSearchUrls()
        );
    }

    public function testMultipleFilterQueries(): void
    {
        $searchBuilder = $this->getDataverseSearchBuilder()
            ->addFilterQuery('publicationDate', '2016')
            ->addFilterQuery('publicationStatus', 'Published');

        $this->assertEquals(
            [self::SEARCH_URL . 'q=*&fq=publicationDate:2016+publicationStatus:Published'],
            $searchBuilder->getSearchUrls()
        );
    }

    public function testFullParamsSearch(): void
    {
        $searchBuilder = $this->getDataverseSearchBuilder()
            ->addQuery('foo')
            ->addType('dataset')
            ->addFilterQuery('publicationStatus', 'Published');

        $this->assertEquals(
            [self::SEARCH_URL . 'q=foo&type=dataset&fq=publicationStatus:Published'],
            $searchBuilder->getSearchUrls()
        );
    }

    public function testLargeNumberOfFiltersGenerateMultipleUrls(): void
    {
        $largeNumberOfFilters = 1000;
        $searchBuilder = $this->getDataverseSearchBuilder();
        for ($i = 0; $i < $largeNumberOfFilters; $i++) {
            $searchBuilder->addFilterQuery('publicationStatus', 'Published');
        }

        $searchUrls = $searchBuilder->getSearchUrls();

        $this->assertGreaterThan(1, count($searchUrls));
        foreach ($searchUrls as $searchUrl) {
            $this->assertStringContainsString(self::SEARCH_URL, $searchUrl);
        }
    }

    public function testConnectionTimeoutIsReportedAsServiceUnavailable(): void
    {
        $mockHandler = new MockHandler([
            new ConnectException(
                'Connection timed out with infrastructure details',
                new Request('GET', self::SEARCH_URL)
            )
        ]);
        $searchBuilder = $this->getDataverseSearchBuilder(new Client(['handler' => $mockHandler]));

        try {
            $searchBuilder->search();
            $this->fail('A DataverseException was expected');
        } catch (DataverseException $exception) {
            $this->assertSame(503, $exception->getCode());
            $this->assertSame('plugins.generic.dataverse.error.unavailable', $exception->getUserMessageKey());
            $this->assertStringNotContainsString('infrastructure details', $exception->getMessage());
        }
    }
}
