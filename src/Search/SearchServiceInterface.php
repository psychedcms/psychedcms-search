<?php

declare(strict_types=1);

namespace PsychedCms\Search\Search;

interface SearchServiceInterface
{
    public function search(
        string $entityClass,
        string $query,
        string $locale,
        int $page = 1,
        int $perPage = 20,
        array $filters = [],
    ): SearchResult;
}
