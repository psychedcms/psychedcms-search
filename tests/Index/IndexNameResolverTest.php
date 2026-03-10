<?php

declare(strict_types=1);

namespace PsychedCms\Search\Tests\Index;

use PHPUnit\Framework\TestCase;
use PsychedCms\Search\Attribute\Indexed;
use PsychedCms\Search\Index\IndexNameResolver;
use PsychedCms\Search\Indexing\EntityMetadataReader;
use PsychedCms\Search\Tests\Fixtures\IndexedEntity;
use PsychedCms\Search\Tests\Fixtures\NonIndexedEntity;

final class IndexNameResolverTest extends TestCase
{
    public function testResolveDefaultName(): void
    {
        $metadataReader = $this->createMock(EntityMetadataReader::class);
        $metadataReader->method('getIndexedAttribute')
            ->with(IndexedEntity::class)
            ->willReturn(new Indexed());

        $resolver = new IndexNameResolver($metadataReader, 'psychedcms_');

        self::assertSame('psychedcms_indexedentity', $resolver->resolve(IndexedEntity::class));
    }

    public function testResolveCustomName(): void
    {
        $metadataReader = $this->createMock(EntityMetadataReader::class);
        $metadataReader->method('getIndexedAttribute')
            ->with(IndexedEntity::class)
            ->willReturn(new Indexed(indexName: 'articles'));

        $resolver = new IndexNameResolver($metadataReader, 'myapp_');

        self::assertSame('myapp_articles', $resolver->resolve(IndexedEntity::class));
    }

    public function testResolveThrowsForNonIndexedEntity(): void
    {
        $metadataReader = $this->createMock(EntityMetadataReader::class);
        $metadataReader->method('getIndexedAttribute')
            ->with(NonIndexedEntity::class)
            ->willReturn(null);

        $resolver = new IndexNameResolver($metadataReader, 'psychedcms_');

        $this->expectException(\InvalidArgumentException::class);
        $resolver->resolve(NonIndexedEntity::class);
    }

    public function testGetPrefix(): void
    {
        $metadataReader = $this->createMock(EntityMetadataReader::class);
        $resolver = new IndexNameResolver($metadataReader, 'test_');

        self::assertSame('test_', $resolver->getPrefix());
    }
}
