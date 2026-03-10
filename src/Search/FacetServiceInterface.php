<?php

declare(strict_types=1);

namespace PsychedCms\Search\Search;

interface FacetServiceInterface
{
    /**
     * @return array<FacetResult>
     */
    public function getFacets(string $entityClass, string $locale, int $size = 50): array;
}
