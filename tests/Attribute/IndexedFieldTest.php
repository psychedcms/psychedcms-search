<?php

declare(strict_types=1);

namespace PsychedCms\Search\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use PsychedCms\Search\Attribute\IndexedField;

final class IndexedFieldTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $field = new IndexedField();

        self::assertNull($field->type);
        self::assertNull($field->analyzer);
        self::assertNull($field->boost);
        self::assertFalse($field->autocomplete);
        self::assertFalse($field->filterable);
        self::assertFalse($field->sortable);
        self::assertFalse($field->facetable);
        self::assertNull($field->properties);
    }

    public function testFullyConfigured(): void
    {
        $field = new IndexedField(
            type: 'text',
            analyzer: 'french',
            boost: 3.0,
            autocomplete: true,
            filterable: true,
            sortable: true,
            facetable: true,
            properties: ['sub' => ['type' => 'keyword']],
        );

        self::assertSame('text', $field->type);
        self::assertSame('french', $field->analyzer);
        self::assertSame(3.0, $field->boost);
        self::assertTrue($field->autocomplete);
        self::assertTrue($field->filterable);
        self::assertTrue($field->sortable);
        self::assertTrue($field->facetable);
        self::assertSame(['sub' => ['type' => 'keyword']], $field->properties);
    }

    public function testAttributeOnProperty(): void
    {
        $reflectionClass = new \ReflectionClass(\PsychedCms\Search\Tests\Fixtures\IndexedEntity::class);
        $titleProperty = $reflectionClass->getProperty('title');
        $attributes = $titleProperty->getAttributes(IndexedField::class);

        self::assertCount(1, $attributes);
        $instance = $attributes[0]->newInstance();
        self::assertSame(3.0, $instance->boost);
        self::assertTrue($instance->autocomplete);
    }

    public function testNoAttributeOnNonIndexedProperty(): void
    {
        $reflectionClass = new \ReflectionClass(\PsychedCms\Search\Tests\Fixtures\IndexedEntity::class);
        $property = $reflectionClass->getProperty('notIndexed');
        $attributes = $property->getAttributes(IndexedField::class);

        self::assertCount(0, $attributes);
    }
}
