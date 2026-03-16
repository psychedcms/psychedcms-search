<?php

declare(strict_types=1);

namespace PsychedCms\Search\Search;

use PsychedCms\Elasticsearch\Client\ElasticsearchClientInterface;
use PsychedCms\Elasticsearch\Index\IndexNameResolver;
use PsychedCms\Elasticsearch\Indexing\EntityMetadataReader;
use Psr\Log\LoggerInterface;

final class AutocompleteService implements AutocompleteServiceInterface
{
    private const MIN_PREFIX_LENGTH = 2;

    public function __construct(
        private readonly ElasticsearchClientInterface $client,
        private readonly EntityMetadataReader $metadataReader,
        private readonly IndexNameResolver $nameResolver,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function suggest(string $entityClass, string $prefix, string $locale, int $size = 10): array
    {
        if (mb_strlen($prefix) < self::MIN_PREFIX_LENGTH) {
            return [];
        }

        $indexName = $this->nameResolver->resolve($entityClass);
        $fields = $this->metadataReader->getIndexedFields($entityClass);

        // Find autocomplete-enabled fields
        $autocompleteFields = [];
        foreach ($fields as $name => $attribute) {
            if ($attribute->autocomplete) {
                $autocompleteFields[] = $name;
            }
        }

        if ($autocompleteFields === []) {
            return [];
        }

        $query = $this->buildQuery($prefix, $locale, $autocompleteFields, $size);

        $this->logger?->debug('Executing autocomplete', [
            'index' => $indexName,
            'prefix' => $prefix,
            'locale' => $locale,
        ]);

        $response = $this->client->search($indexName, $query);

        return $this->parseResponse($response);
    }

    /**
     * @param array<string> $autocompleteFields
     * @return array<string, mixed>
     */
    private function buildQuery(string $prefix, string $locale, array $autocompleteFields, int $size): array
    {
        $shouldClauses = [];

        foreach ($autocompleteFields as $field) {
            $shouldClauses[] = [
                'match_phrase_prefix' => [
                    "{$field}.autocomplete" => [
                        'query' => $prefix,
                        'max_expansions' => 50,
                    ],
                ],
            ];

            // Also try keyword prefix for exact matching
            if (\in_array($field, ['title', 'name'], true)) {
                $shouldClauses[] = [
                    'prefix' => [
                        "{$field}.raw" => [
                            'value' => $prefix,
                            'case_insensitive' => true,
                        ],
                    ],
                ];
            }
        }

        return [
            'size' => $size,
            '_source' => ['_content_type', '_slug', '_locale'] + array_map(
                fn (string $f) => $f,
                $autocompleteFields
            ),
            'query' => [
                'bool' => [
                    'must' => [
                        ['term' => ['_locale' => $locale]],
                    ],
                    'should' => $shouldClauses,
                    'minimum_should_match' => 1,
                ],
            ],
            'sort' => [
                '_score' => ['order' => 'desc'],
            ],
        ];
    }

    /**
     * @return array<array{text: string, slug: string, contentType: string, id: int|string, score: float}>
     */
    private function parseResponse(array $response): array
    {
        $hits = $response['hits']['hits'] ?? [];
        $suggestions = [];
        $seen = [];

        foreach ($hits as $hit) {
            $source = $hit['_source'] ?? [];

            // Try common text fields
            $text = $source['title'] ?? $source['name'] ?? null;
            if ($text === null) {
                continue;
            }

            $contentType = $source['_content_type'] ?? '';
            $dedupeKey = $contentType . ':' . $text;

            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;

            $suggestions[] = [
                'text' => $text,
                'slug' => $source['_slug'] ?? '',
                'contentType' => $contentType,
                'id' => $hit['_id'] ?? '',
                'score' => (float) ($hit['_score'] ?? 0.0),
            ];
        }

        return $suggestions;
    }
}
