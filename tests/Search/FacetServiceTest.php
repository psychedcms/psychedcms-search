<?php

declare(strict_types=1);

namespace PsychedCms\Search\Tests\Search;

use PHPUnit\Framework\TestCase;
use PsychedCms\Search\Attribute\IndexedField;
use PsychedCms\Search\Client\ElasticsearchClientInterface;
use PsychedCms\Search\Index\IndexNameResolver;
use PsychedCms\Search\Indexing\EntityMetadataReader;
use PsychedCms\Search\Search\FacetService;
use PsychedCms\Search\Tests\Fixtures\IndexedEntity;

final class FacetServiceTest extends TestCase
{
    private ElasticsearchClientInterface $client;
    private EntityMetadataReader $metadataReader;
    private IndexNameResolver $nameResolver;
    private FacetService $service;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ElasticsearchClientInterface::class);
        $this->metadataReader = $this->createMock(EntityMetadataReader::class);
        $this->nameResolver = $this->createMock(IndexNameResolver::class);

        $this->service = new FacetService(
            $this->client,
            $this->metadataReader,
            $this->nameResolver,
        );
    }

    public function testGetFacetsBuildsTermsAggregation(): void
    {
        $this->nameResolver->method('resolve')->willReturn('psychedcms_indexedentity');
        $this->metadataReader->method('getIndexedFields')->willReturn([
            'tags' => new IndexedField(facetable: true, filterable: true),
            'title' => new IndexedField(boost: 3.0), // not facetable
        ]);

        $this->client->expects(self::once())
            ->method('search')
            ->with(
                'psychedcms_indexedentity',
                self::callback(function (array $body): bool {
                    self::assertSame(0, $body['size']);
                    self::assertArrayHasKey('aggs', $body);
                    self::assertArrayHasKey('tags', $body['aggs']);
                    self::assertArrayNotHasKey('title', $body['aggs']);

                    // Locale filter
                    self::assertSame('en', $body['query']['bool']['filter'][0]['term']['_locale']);

                    return true;
                })
            )
            ->willReturn([
                'aggregations' => [
                    'tags' => [
                        'buckets' => [
                            ['key' => 'PHP', 'doc_count' => 10],
                            ['key' => 'Symfony', 'doc_count' => 5],
                        ],
                    ],
                ],
            ]);

        $results = $this->service->getFacets(IndexedEntity::class, 'en');

        self::assertCount(1, $results);
        self::assertSame('tags', $results[0]->fieldName);
        self::assertSame('terms', $results[0]->type);
        self::assertCount(2, $results[0]->buckets);
        self::assertSame('PHP', $results[0]->buckets[0]['key']);
        self::assertSame(10, $results[0]->buckets[0]['count']);
    }

    public function testGetFacetsReturnsEmptyWhenNoFacetableFields(): void
    {
        $this->nameResolver->method('resolve')->willReturn('psychedcms_indexedentity');
        $this->metadataReader->method('getIndexedFields')->willReturn([
            'title' => new IndexedField(boost: 3.0),
        ]);

        $results = $this->service->getFacets(IndexedEntity::class, 'en');

        self::assertSame([], $results);
    }

    public function testGetFacetsWithDateHistogram(): void
    {
        $this->nameResolver->method('resolve')->willReturn('psychedcms_indexedentity');
        $this->metadataReader->method('getIndexedFields')->willReturn([
            'publishedAt' => new IndexedField(type: 'date', facetable: true),
        ]);

        $this->client->method('search')
            ->willReturn([
                'aggregations' => [
                    'publishedAt' => [
                        'buckets' => [
                            ['key_as_string' => '2025', 'key' => 1735689600000, 'doc_count' => 3],
                            ['key_as_string' => '2026', 'key' => 1767225600000, 'doc_count' => 7],
                        ],
                    ],
                ],
            ]);

        $results = $this->service->getFacets(IndexedEntity::class, 'en');

        self::assertCount(1, $results);
        self::assertSame('date_histogram', $results[0]->type);
        self::assertSame('2025', $results[0]->buckets[0]['key']);
    }
}
