<?php

use APP\plugins\generic\dataverse\dataverseAPI\search\DataverseSearchBuilder;
use APP\plugins\generic\dataverse\classes\dataverseConfiguration\DataverseConfiguration;

class DataverseSearchBuilderTest extends PHPUnit\Framework\TestCase
{
    private const DATAVERSE_URL = 'https://test.dataverse.org/dataverse/testDataverse';
    private const SEARCH_URL = 'https://test.dataverse.org/api/search?';

    private function getDataverseSearchBuilder(): DataverseSearchBuilder
    {
        $configuration = new DataverseConfiguration();
        $httpClient = new \GuzzleHttp\Client();

        $configuration->setDataverseUrl(self::DATAVERSE_URL);

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
}
