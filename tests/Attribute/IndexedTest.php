<?php

declare(strict_types=1);

namespace PsychedCms\Search\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use PsychedCms\Search\Attribute\Indexed;

final class IndexedTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $indexed = new Indexed();

        self::assertNull($indexed->indexName);
    }

    public function testCustomIndexName(): void
    {
        $indexed = new Indexed(indexName: 'custom_index');

        self::assertSame('custom_index', $indexed->indexName);
    }

    public function testAttributeOnClass(): void
    {
        $reflectionClass = new \ReflectionClass(\PsychedCms\Search\Tests\Fixtures\IndexedEntity::class);
        $attributes = $reflectionClass->getAttributes(Indexed::class);

        self::assertCount(1, $attributes);
        $instance = $attributes[0]->newInstance();
        self::assertNull($instance->indexName);
    }

    public function testAttributeNotOnNonIndexedClass(): void
    {
        $reflectionClass = new \ReflectionClass(\PsychedCms\Search\Tests\Fixtures\NonIndexedEntity::class);
        $attributes = $reflectionClass->getAttributes(Indexed::class);

        self::assertCount(0, $attributes);
    }
}
