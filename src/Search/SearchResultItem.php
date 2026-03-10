<?php

declare(strict_types=1);

namespace PsychedCms\Search\Search;

final readonly class SearchResultItem
{
    public function __construct(
        public int|string $id,
        public string $contentType,
        public string $locale,
        public float $score,
        public array $source = [],
    ) {
    }
}
