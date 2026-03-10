<?php

declare(strict_types=1);

namespace PsychedCms\Search\Tests\Indexing;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PsychedCms\Search\Attribute\IndexedField;
use PsychedCms\Search\Indexing\DocumentBuilder;
use PsychedCms\Search\Indexing\EntityMetadataReader;
use PsychedCms\Search\Tests\Fixtures\IndexedEntity;
use PsychedCms\Search\Tests\Fixtures\TagFixture;

final class DocumentBuilderTest extends TestCase
{
    private EntityMetadataReader $metadataReader;
    private DocumentBuilder $builder;

    protected function setUp(): void
    {
        $this->metadataReader = $this->createMock(EntityMetadataReader::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $this->builder = new DocumentBuilder($this->metadataReader, $entityManager);
    }

    public function testBuildIncludesMetadata(): void
    {
        $this->metadataReader->method('getIndexedFields')
            ->willReturn([]);

        $entity = (new IndexedEntity())->setId(1)->setTitle('Test');

        $document = $this->builder->build($entity, 'en', 'en');

        self::assertSame('indexedentity', $document['_content_type']);
        self::assertSame('en', $document['_locale']);
    }

    public function testBuildIncludesStringField(): void
    {
        $this->metadataReader->method('getIndexedFields')
            ->willReturn([
                'title' => new IndexedField(boost: 3.0),
            ]);

        $entity = (new IndexedEntity())->setId(1)->setTitle('My Title');

        $document = $this->builder->build($entity, 'en', 'en');

        self::assertSame('My Title', $document['title']);
    }

    public function testBuildFormatsDateTimeAsIso8601(): void
    {
        $date = new \DateTimeImmutable('2026-03-10T14:00:00+00:00');

        $this->metadataReader->method('getIndexedFields')
            ->willReturn([
                'publishedAt' => new IndexedField(type: 'date'),
            ]);

        $entity = (new IndexedEntity())->setId(1)->setTitle('Test')->setPublishedAt($date);

        $document = $this->builder->build($entity, 'en', 'en');

        self::assertSame('2026-03-10T14:00:00+00:00', $document['publishedAt']);
    }

    public function testBuildNormalizesGeoPoint(): void
    {
        $this->metadataReader->method('getIndexedFields')
            ->willReturn([
                'location' => new IndexedField(type: 'geo_point'),
            ]);

        $entity = (new IndexedEntity())
            ->setId(1)
            ->setTitle('Test')
            ->setLocation(['lat' => 47.2, 'lng' => -1.5]);

        $document = $this->builder->build($entity, 'en', 'en');

        self::assertSame(['lat' => 47.2, 'lon' => -1.5], $document['location']);
    }

    public function testBuildSkipsNullFields(): void
    {
        $this->metadataReader->method('getIndexedFields')
            ->willReturn([
                'description' => new IndexedField(),
            ]);

        $entity = (new IndexedEntity())->setId(1)->setTitle('Test');

        $document = $this->builder->build($entity, 'en', 'en');

        self::assertArrayNotHasKey('description', $document);
    }

    public function testBuildNormalizesCollection(): void
    {
        $this->metadataReader->method('getIndexedFields')
            ->willReturn([
                'tags' => new IndexedField(facetable: true),
            ]);

        $entity = (new IndexedEntity())->setId(1)->setTitle('Test');
        // Use reflection to set tags collection with fixture objects
        $reflection = new \ReflectionClass($entity);
        $property = $reflection->getProperty('tags');
        $property->setAccessible(true);
        $property->setValue($entity, new ArrayCollection([
            new TagFixture(1, 'PHP'),
            new TagFixture(2, 'Symfony'),
        ]));

        $document = $this->builder->build($entity, 'en', 'en');

        self::assertCount(2, $document['tags']);
        self::assertSame(1, $document['tags'][0]['id']);
        self::assertSame('PHP', $document['tags'][0]['name']);
    }
}
