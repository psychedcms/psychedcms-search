<?php

declare(strict_types=1);

namespace PsychedCms\Search\Search;

final readonly class FacetResult
{
    /**
     * @param array<array{key: string, count: int}> $buckets
     * @param string|null $group The source relation name for grouping (null for direct entity fields)
     */
    public function __construct(
        public string $fieldName,
        public string $type,
        public array $buckets,
        public ?string $group = null,
    ) {
    }
}
