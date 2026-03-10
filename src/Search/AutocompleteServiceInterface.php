<?php

declare(strict_types=1);

namespace PsychedCms\Search\Search;

interface AutocompleteServiceInterface
{
    /**
     * @return array<array{text: string, slug: string, contentType: string, id: int|string, score: float}>
     */
    public function suggest(string $entityClass, string $prefix, string $locale, int $size = 10): array;
}
