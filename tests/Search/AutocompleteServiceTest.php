<?php

declare(strict_types=1);

namespace PsychedCms\Search\Tests\Search;

use PHPUnit\Framework\TestCase;
use PsychedCms\Search\Attribute\IndexedField;
use PsychedCms\Search\Client\ElasticsearchClientInterface;
use PsychedCms\Search\Index\IndexNameResolver;
use PsychedCms\Search\Indexing\EntityMetadataReader;
use PsychedCms\Search\Search\AutocompleteService;
use PsychedCms\Search\Tests\Fixtures\IndexedEntity;

final class AutocompleteServiceTest extends TestCase
{
    private ElasticsearchClientInterface $client;
    private EntityMetadataReader $metadataReader;
    private IndexNameResolver $nameResolver;
    private AutocompleteService $service;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ElasticsearchClientInterface::class);
        $this->metadataReader = $this->createMock(EntityMetadataReader::class);
        $this->nameResolver = $this->createMock(IndexNameResolver::class);

        $this->service = new AutocompleteService(
            $this->client,
            $this->metadataReader,
            $this->nameResolver,
        );
    }

    public function testSuggestReturnsSuggestions(): void
    {
        $this->nameResolver->method('resolve')->willReturn('psychedcms_indexedentity');
        $this->metadataReader->method('getIndexedFields')->willReturn([
            'title' => new IndexedField(boost: 3.0, autocomplete: true),
            'description' => new IndexedField(),
        ]);

        $this->client->expects(self::once())
            ->method('search')
            ->willReturn([
                'hits' => [
                    'hits' => [
                        [
                            '_id' => 'indexedentity_1_en',
                            '_score' => 2.5,
                            '_source' => [
                                'title' => 'Psychology Today',
                                '_slug' => 'psychology-today',
                                '_content_type' => 'indexedentity',
                                '_locale' => 'en',
                            ],
                        ],
                    ],
                ],
            ]);

        $suggestions = $this->service->suggest(IndexedEntity::class, 'psy', 'en');

        self::assertCount(1, $suggestions);
        self::assertSame('Psychology Today', $suggestions[0]['text']);
        self::assertSame('psychology-today', $suggestions[0]['slug']);
        self::assertSame('indexedentity', $suggestions[0]['contentType']);
    }

    public function testSuggestReturnsEmptyForShortPrefix(): void
    {
        $suggestions = $this->service->suggest(IndexedEntity::class, 'p', 'en');

        self::assertSame([], $suggestions);
    }

    public function testSuggestReturnsEmptyWhenNoAutocompleteFields(): void
    {
        $this->nameResolver->method('resolve')->willReturn('psychedcms_indexedentity');
        $this->metadataReader->method('getIndexedFields')->willReturn([
            'description' => new IndexedField(),
        ]);

        $suggestions = $this->service->suggest(IndexedEntity::class, 'test', 'en');

        self::assertSame([], $suggestions);
    }

    public function testSuggestDeduplicates(): void
    {
        $this->nameResolver->method('resolve')->willReturn('psychedcms_indexedentity');
        $this->metadataReader->method('getIndexedFields')->willReturn([
            'title' => new IndexedField(autocomplete: true),
        ]);

        $this->client->method('search')
            ->willReturn([
                'hits' => [
                    'hits' => [
                        [
                            '_id' => '1',
                            '_score' => 2.0,
                            '_source' => ['title' => 'Same Title', '_content_type' => 'indexedentity', '_slug' => 'same-1', '_locale' => 'en'],
                        ],
                        [
                            '_id' => '2',
                            '_score' => 1.5,
                            '_source' => ['title' => 'Same Title', '_content_type' => 'indexedentity', '_slug' => 'same-2', '_locale' => 'en'],
                        ],
                    ],
                ],
            ]);

        $suggestions = $this->service->suggest(IndexedEntity::class, 'Same', 'en');

        self::assertCount(1, $suggestions);
    }
}
