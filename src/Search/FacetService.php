<?php

declare(strict_types=1);

namespace PsychedCms\Search\Search;

use PsychedCms\Elasticsearch\Client\ElasticsearchClientInterface;
use PsychedCms\Elasticsearch\Index\IndexNameResolver;
use PsychedCms\Elasticsearch\Indexing\EntityMetadataReader;
use Psr\Log\LoggerInterface;

final class FacetService implements FacetServiceInterface
{
    public function __construct(
        private readonly ElasticsearchClientInterface $client,
        private readonly EntityMetadataReader $metadataReader,
        private readonly IndexNameResolver $nameResolver,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function getFacets(string $entityClass, string $locale, int $size = 50): array
    {
        $indexName = $this->nameResolver->resolveForLocale($entityClass, $locale);
        $fields = $this->metadataReader->getIndexedFields($entityClass);

        // Discover direct facetable fields
        $facetableFields = [];
        foreach ($fields as $name => $attribute) {
            if ($attribute->facetable) {
                $facetableFields[$name] = $attribute;
            }
        }

        // Discover relation facetable fields (useRelationFacets=true)
        $relationFacetFields = $this->metadataReader->getRelationFacetFields($entityClass);

        if ($facetableFields === [] && $relationFacetFields === []) {
            return [];
        }

        $query = $this->buildQuery($locale, $facetableFields, $size, $relationFacetFields);

        $this->logger?->debug('Executing facet query', [
            'index' => $indexName,
            'locale' => $locale,
            'fields' => array_keys($facetableFields),
            'relationFields' => array_keys($relationFacetFields),
        ]);

        $response = $this->client->search($indexName, $query);

        return $this->parseResponse($response, $facetableFields, $relationFacetFields);
    }

    /**
     * @param array<string, \PsychedCms\Elasticsearch\Attribute\IndexedField> $facetableFields
     * @param array<string, array{path: string, attribute: \PsychedCms\Elasticsearch\Attribute\IndexedField, relationType: string}> $relationFacetFields
     * @return array<string, mixed>
     */
    private function buildQuery(string $locale, array $facetableFields, int $size, array $relationFacetFields = []): array
    {
        $aggregations = [];

        foreach ($facetableFields as $name => $attribute) {
            $esType = $attribute->type;

            if ($esType === 'date') {
                // Date histogram aggregation
                $aggregations[$name] = [
                    'date_histogram' => [
                        'field' => $name,
                        'calendar_interval' => 'year',
                        'format' => 'yyyy',
                        'min_doc_count' => 1,
                    ],
                ];
            } else {
                // Terms aggregation — use .name sub-field for nested types
                $fieldPath = \in_array($esType, ['nested', 'object'], true)
                    ? "{$name}.name"
                    : $name;

                $aggregations[$name] = [
                    'terms' => [
                        'field' => $fieldPath,
                        'size' => $size,
                    ],
                ];
            }
        }

        // Relation facetable fields
        foreach ($relationFacetFields as $key => $config) {
            $aggregations[$key] = [
                'terms' => [
                    'field' => $config['path'],
                    'size' => $size,
                ],
            ];
        }

        return [
            'size' => 0,
            'query' => [
                'bool' => [
                    'filter' => [
                        ['term' => ['_locale' => $locale]],
                    ],
                ],
            ],
            'aggs' => $aggregations,
        ];
    }

    /**
     * @param array<string, \PsychedCms\Elasticsearch\Attribute\IndexedField> $facetableFields
     * @param array<string, array{path: string, attribute: \PsychedCms\Elasticsearch\Attribute\IndexedField, relationType: string}> $relationFacetFields
     * @return array<FacetResult>
     */
    private function parseResponse(array $response, array $facetableFields, array $relationFacetFields = []): array
    {
        $aggregations = $response['aggregations'] ?? [];
        $results = [];

        foreach ($facetableFields as $name => $attribute) {
            if (!isset($aggregations[$name]['buckets'])) {
                continue;
            }

            $buckets = array_map(
                fn (array $bucket): array => [
                    'key' => (string) ($bucket['key_as_string'] ?? $bucket['key']),
                    'count' => (int) $bucket['doc_count'],
                ],
                $aggregations[$name]['buckets']
            );

            $type = $attribute->type === 'date' ? 'date_histogram' : 'terms';

            $results[] = new FacetResult($name, $type, $buckets);
        }

        // Parse relation facets — use the last segment as the display field name
        foreach ($relationFacetFields as $key => $config) {
            if (!isset($aggregations[$key]['buckets'])) {
                continue;
            }

            $buckets = array_map(
                fn (array $bucket): array => [
                    'key' => (string) ($bucket['key_as_string'] ?? $bucket['key']),
                    'count' => (int) $bucket['doc_count'],
                ],
                $aggregations[$key]['buckets']
            );

            // Extract relation name and field name from key (e.g., 'release.genres')
            $parts = explode('.', $key);
            $relation = $parts[0];
            $fieldName = $parts[1] ?? $parts[0];

            $results[] = new FacetResult($fieldName, 'terms', $buckets, $relation);
        }

        return $results;
    }
}
