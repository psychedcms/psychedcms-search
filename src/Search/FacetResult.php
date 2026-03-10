<?php

declare(strict_types=1);

namespace PsychedCms\Search\Search;

final readonly class FacetResult
{
    /**
     * @param array<array{key: string, count: int}> $buckets
     */
    public function __construct(
        public string $fieldName,
        public string $type,
        public array $buckets,
    ) {
    }
}
