<?php

declare(strict_types=1);

namespace PsychedCms\Search\Tests\Search;

use PHPUnit\Framework\TestCase;
use PsychedCms\Search\Attribute\Indexed;
use PsychedCms\Search\Attribute\IndexedField;
use PsychedCms\Search\Client\ElasticsearchClientInterface;
use PsychedCms\Search\Index\IndexNameResolver;
use PsychedCms\Search\Indexing\EntityMetadataReader;
use PsychedCms\Search\Search\SearchService;
use PsychedCms\Search\Tests\Fixtures\IndexedEntity;

final class SearchServiceTest extends TestCase
{
    private ElasticsearchClientInterface $client;
    private EntityMetadataReader $metadataReader;
    private IndexNameResolver $nameResolver;
    private SearchService $service;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ElasticsearchClientInterface::class);
        $this->metadataReader = $this->createMock(EntityMetadataReader::class);
        $this->nameResolver = $this->createMock(IndexNameResolver::class);

        $this->service = new SearchService(
            $this->client,
            $this->metadataReader,
            $this->nameResolver,
        );
    }

    public function testSearchBuildsQueryWithBoost(): void
    {
        $this->nameResolver->method('resolve')
            ->with(IndexedEntity::class)
            ->willReturn('psychedcms_indexedentity');

        $this->metadataReader->method('getIndexedFields')
            ->with(IndexedEntity::class)
            ->willReturn([
                'title' => new IndexedField(boost: 3.0, autocomplete: true),
                'description' => new IndexedField(),
            ]);

        $this->client->expects(self::once())
            ->method('search')
            ->with(
                'psychedcms_indexedentity',
                self::callback(function (array $body): bool {
                    // Verify query structure
                    self::assertArrayHasKey('query', $body);
                    $bool = $body['query']['bool'];
                    self::assertArrayHasKey('must', $bool);
                    self::assertArrayHasKey('filter', $bool);

                    // Check locale filter
                    self::assertSame('en', $bool['filter'][0]['term']['_locale']);

                    // Check multi_match with boosted fields
                    $multiMatch = $bool['must'][0]['multi_match'];
                    self::assertSame('test query', $multiMatch['query']);
                    self::assertContains('title^3', $multiMatch['fields']);
                    self::assertContains('description', $multiMatch['fields']);
                    self::assertSame('AUTO', $multiMatch['fuzziness']);

                    return true;
                })
            )
            ->willReturn([
                'hits' => [
                    'total' => ['value' => 1],
                    'hits' => [
                        [
                            '_id' => 'indexedentity_1_en',
                            '_score' => 1.5,
                            '_source' => [
                                '_content_type' => 'indexedentity',
                                '_locale' => 'en',
                                'title' => 'Test',
                            ],
                        ],
                    ],
                ],
            ]);

        $result = $this->service->search(IndexedEntity::class, 'test query', 'en');

        self::assertSame(1, $result->total);
        self::assertCount(1, $result->items);
        self::assertSame(1, $result->page);
        self::assertSame(20, $result->perPage);
        self::assertSame(1.5, $result->items[0]->score);
    }

    public function testSearchWithEmptyQueryUsesMatchAll(): void
    {
        $this->nameResolver->method('resolve')->willReturn('psychedcms_indexedentity');
        $this->metadataReader->method('getIndexedFields')->willReturn([]);

        $this->client->expects(self::once())
            ->method('search')
            ->with(
                'psychedcms_indexedentity',
                self::callback(function (array $body): bool {
                    $must = $body['query']['bool']['must'][0];
                    self::assertArrayHasKey('match_all', $must);

                    return true;
                })
            )
            ->willReturn([
                'hits' => ['total' => ['value' => 0], 'hits' => []],
            ]);

        $result = $this->service->search(IndexedEntity::class, '', 'en');

        self::assertSame(0, $result->total);
    }

    public function testSearchPagination(): void
    {
        $this->nameResolver->method('resolve')->willReturn('psychedcms_indexedentity');
        $this->metadataReader->method('getIndexedFields')->willReturn([]);

        $this->client->expects(self::once())
            ->method('search')
            ->with(
                'psychedcms_indexedentity',
                self::callback(function (array $body): bool {
                    self::assertSame(10, $body['from']); // (2-1) * 10
                    self::assertSame(10, $body['size']);

                    return true;
                })
            )
            ->willReturn([
                'hits' => ['total' => ['value' => 0], 'hits' => []],
            ]);

        $result = $this->service->search(IndexedEntity::class, '', 'en', 2, 10);

        self::assertSame(2, $result->page);
        self::assertSame(10, $result->perPage);
    }
}
