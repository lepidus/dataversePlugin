<?php

import('plugins.generic.dataverse.dataverseAPI.search.DataverseSearchBuilder');

class DataverseSearchBuilderTest extends PHPUnit\Framework\TestCase
{
    private function getDataverseSearchBuilder(): DataverseSearchBuilder
    {
        $configuration = new DataverseConfiguration();
        $httpClient = new \GuzzleHttp\Client();

        $configuration->setDataverseUrl('https://test.dataverse.org/dataverse/testDataverse');

        return new DataverseSearchBuilder($configuration, $httpClient);
    }

    public function testEmptyQuery(): void
    {
        $searchBuilder = $this->getDataverseSearchBuilder();
        $this->assertEquals('q=*', $searchBuilder->getParams());
    }

    public function testSingleQuery(): void
    {
        $searchBuilder = $this->getDataverseSearchBuilder();
        $searchBuilder->addQuery('test');
        $this->assertEquals('q=test', $searchBuilder->getParams());
    }

    public function testMultipleQueries(): void
    {
        $searchBuilder = $this->getDataverseSearchBuilder()
            ->addQuery('title:test')
            ->addQuery('language:English');

        $this->assertEquals('q=title:test+language:English', $searchBuilder->getParams());
    }

    public function testSingleType(): void
    {
        $searchBuilder = $this->getDataverseSearchBuilder();
        $searchBuilder->addType('dataset');
        $this->assertEquals('q=*&type=dataset', $searchBuilder->getParams());
    }

    public function testMultipleTypes(): void
    {
        $searchBuilder = $this->getDataverseSearchBuilder()
            ->addType('dataset')
            ->addType('file');

        $this->assertEquals('q=*&type=dataset&type=file', $searchBuilder->getParams());
    }

    public function testSingleFilterQuery(): void
    {
        $searchBuilder = $this->getDataverseSearchBuilder();
        $searchBuilder->addFilterQuery('publicationDate', '2016');
        $this->assertEquals('q=*&fq=publicationDate:2016', $searchBuilder->getParams());
    }

    public function testMultipleFilterQueries(): void
    {
        $searchBuilder = $this->getDataverseSearchBuilder()
            ->addFilterQuery('publicationDate', '2016')
            ->addFilterQuery('publicationStatus', 'Published');

        $this->assertEquals('q=*&fq=publicationDate:2016+publicationStatus:Published', $searchBuilder->getParams());
    }

    public function testFullParamsSearch(): void
    {
        $searchBuilder = $this->getDataverseSearchBuilder()
            ->addQuery('foo')
            ->addType('dataset')
            ->addFilterQuery('publicationStatus', 'Published');

        $this->assertEquals(
            'q=foo&type=dataset&fq=publicationStatus:Published',
            $searchBuilder->getParams()
        );
    }

    public function testBuildDataverseSearchUrl(): void
    {
        $searchBuilder = $this->getDataverseSearchBuilder()
            ->addQuery('foo')
            ->addType('dataset')
            ->addType('file')
            ->addFilterQuery('publicationStatus', 'Published');

        $this->assertEquals(
            'https://test.dataverse.org/api/search?q=foo&type=dataset&type=file&fq=publicationStatus:Published',
            $searchBuilder->getSearchUrl()
        );
    }
}
