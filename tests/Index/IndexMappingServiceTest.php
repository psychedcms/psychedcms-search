<?php

declare(strict_types=1);

namespace PsychedCms\Search\Tests\Index;

use PHPUnit\Framework\TestCase;
use PsychedCms\Search\Attribute\IndexedField;
use PsychedCms\Search\Index\IndexMappingService;
use PsychedCms\Search\Indexing\EntityMetadataReader;
use PsychedCms\Search\Tests\Fixtures\IndexedEntity;

final class IndexMappingServiceTest extends TestCase
{
    private EntityMetadataReader $metadataReader;
    private IndexMappingService $service;

    protected function setUp(): void
    {
        $this->metadataReader = $this->createMock(EntityMetadataReader::class);
        $this->service = new IndexMappingService($this->metadataReader);
    }

    public function testGetMappingContainsMetadataFields(): void
    {
        $this->metadataReader->method('getIndexedFields')
            ->with(IndexedEntity::class)
            ->willReturn([]);

        $mapping = $this->service->getMappingForEntity(IndexedEntity::class);

        self::assertArrayHasKey('properties', $mapping);
        $properties = $mapping['properties'];

        self::assertArrayHasKey('_content_type', $properties);
        self::assertArrayHasKey('_slug', $properties);
        self::assertArrayHasKey('_status', $properties);
        self::assertArrayHasKey('_locale', $properties);
        self::assertArrayHasKey('_created_at', $properties);
        self::assertArrayHasKey('_updated_at', $properties);

        self::assertSame('keyword', $properties['_content_type']['type']);
        self::assertSame('date', $properties['_created_at']['type']);
    }

    public function testTextFieldWithAutocomplete(): void
    {
        $this->metadataReader->method('getIndexedFields')
            ->willReturn([
                'title' => new IndexedField(boost: 3.0, autocomplete: true),
            ]);
        $this->metadataReader->method('getPropertyType')
            ->with(IndexedEntity::class, 'title')
            ->willReturn('string');

        $mapping = $this->service->getMappingForEntity(IndexedEntity::class);
        $titleMapping = $mapping['properties']['title'];

        self::assertSame('text', $titleMapping['type']);
        self::assertArrayHasKey('fields', $titleMapping);
        self::assertArrayHasKey('autocomplete', $titleMapping['fields']);
        self::assertSame('autocomplete_analyzer', $titleMapping['fields']['autocomplete']['analyzer']);
    }

    public function testFilterableFieldHasRawSubfield(): void
    {
        $this->metadataReader->method('getIndexedFields')
            ->willReturn([
                'status' => new IndexedField(filterable: true),
            ]);
        $this->metadataReader->method('getPropertyType')
            ->willReturn('string');

        $mapping = $this->service->getMappingForEntity(IndexedEntity::class);
        $statusMapping = $mapping['properties']['status'];

        self::assertArrayHasKey('fields', $statusMapping);
        self::assertArrayHasKey('raw', $statusMapping['fields']);
        self::assertSame('keyword', $statusMapping['fields']['raw']['type']);
    }

    public function testDateFieldMapping(): void
    {
        $this->metadataReader->method('getIndexedFields')
            ->willReturn([
                'publishedAt' => new IndexedField(type: 'date', filterable: true),
            ]);

        $mapping = $this->service->getMappingForEntity(IndexedEntity::class);

        self::assertSame('date', $mapping['properties']['publishedAt']['type']);
    }

    public function testGeoPointFieldMapping(): void
    {
        $this->metadataReader->method('getIndexedFields')
            ->willReturn([
                'location' => new IndexedField(type: 'geo_point'),
            ]);

        $mapping = $this->service->getMappingForEntity(IndexedEntity::class);

        self::assertSame('geo_point', $mapping['properties']['location']['type']);
    }

    public function testAutoDetectTypeFromPhpString(): void
    {
        $this->metadataReader->method('getIndexedFields')
            ->willReturn([
                'name' => new IndexedField(),
            ]);
        $this->metadataReader->method('getPropertyType')
            ->willReturn('string');

        $mapping = $this->service->getMappingForEntity(IndexedEntity::class);

        self::assertSame('text', $mapping['properties']['name']['type']);
    }

    public function testAutoDetectTypeFromPhpInt(): void
    {
        $this->metadataReader->method('getIndexedFields')
            ->willReturn([
                'count' => new IndexedField(),
            ]);
        $this->metadataReader->method('getPropertyType')
            ->willReturn('int');

        $mapping = $this->service->getMappingForEntity(IndexedEntity::class);

        self::assertSame('integer', $mapping['properties']['count']['type']);
    }

    public function testGetIndexSettings(): void
    {
        $settings = $this->service->getIndexSettings();

        self::assertArrayHasKey('analysis', $settings);
        self::assertArrayHasKey('analyzer', $settings['analysis']);
        self::assertArrayHasKey('autocomplete_analyzer', $settings['analysis']['analyzer']);
        self::assertArrayHasKey('tokenizer', $settings['analysis']);
        self::assertArrayHasKey('autocomplete_tokenizer', $settings['analysis']['tokenizer']);
        self::assertSame('edge_ngram', $settings['analysis']['tokenizer']['autocomplete_tokenizer']['type']);
    }
}
