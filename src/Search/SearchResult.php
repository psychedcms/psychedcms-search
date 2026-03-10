<?php

declare(strict_types=1);

namespace PsychedCms\Search\Search;

final readonly class SearchResult
{
    /**
     * @param array<SearchResultItem> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }
}
