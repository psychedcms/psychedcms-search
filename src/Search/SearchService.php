<?php

declare(strict_types=1);

namespace PsychedCms\Search\Search;

use PsychedCms\Elasticsearch\Attribute\IndexedField;
use PsychedCms\Elasticsearch\Client\ElasticsearchClientInterface;
use PsychedCms\Elasticsearch\Index\IndexNameResolver;
use PsychedCms\Elasticsearch\Indexing\EntityMetadataReader;
use Psr\Log\LoggerInterface;

final class SearchService implements SearchServiceInterface
{
    public function __construct(
        private readonly ElasticsearchClientInterface $client,
        private readonly EntityMetadataReader $metadataReader,
        private readonly IndexNameResolver $nameResolver,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function search(
        string $entityClass,
        string $query,
        string $locale,
        int $page = 1,
        int $perPage = 20,
        array $filters = [],
    ): SearchResult {
        $indexName = $this->nameResolver->resolveForLocale($entityClass, $locale);
        $fields = $this->metadataReader->getIndexedFields($entityClass);

        $esQuery = $this->buildSearchQuery($query, $locale, $fields, $filters);

        $from = ($page - 1) * $perPage;

        $searchBody = [
            'query' => $esQuery,
            'from' => $from,
            'size' => $perPage,
        ];

        // Add geo sort when geo filter is active
        if (isset($filters['_geo'])) {
            $geoField = $this->resolveGeoField($entityClass);
            $searchBody['sort'] = [
                ['_geo_distance' => [
                    $geoField => [
                        'lat' => (float) $filters['_geo']['lat'],
                        'lon' => (float) $filters['_geo']['lng'],
                    ],
                    'order' => 'asc',
                    'unit' => 'km',
                    'ignore_unmapped' => true,
                ]],
            ];
        }

        $this->logger?->debug('Executing search', [
            'index' => $indexName,
            'query' => $query,
            'locale' => $locale,
        ]);

        $response = $this->client->search($indexName, $searchBody);

        return $this->parseResponse($response, $page, $perPage);
    }

    /**
     * @param array<string, IndexedField> $fields
     * @return array<string, mixed>
     */
    private function buildSearchQuery(string $query, string $locale, array $fields, array $filters): array
    {
        $must = [];
        $filter = [];

        $filter[] = ['term' => ['_locale' => $locale]];

        if ($query !== '') {
            $boostedFields = $this->getBoostedFields($fields);

            $must[] = [
                'multi_match' => [
                    'query' => $query,
                    'fields' => $boostedFields,
                    'type' => 'bool_prefix',
                ],
            ];
        }

        // Apply additional filters
        foreach ($filters as $field => $values) {
            // Geo filter is handled separately
            if ($field === '_geo') {
                continue;
            }

            // Date range filters
            if ($field === '_dateRanges') {
                foreach ($values as $esField => $rangeOps) {
                    $filter[] = ['range' => [$esField => $rangeOps]];
                }
                continue;
            }
            if (!\is_array($values)) {
                $values = [$values];
            }

            // Nested relation fields: wrap in nested query
            if (isset($fields[$field]) && $fields[$field]->type === 'nested') {
                $filter[] = [
                    'nested' => [
                        'path' => $field,
                        'query' => ['terms' => ["{$field}.slug" => $values]],
                    ],
                ];
                continue;
            }

            // Object relation fields: filter on {field}.slug
            if (isset($fields[$field]) && $fields[$field]->type === 'object') {
                $filter[] = ['terms' => ["{$field}.slug" => $values]];
                continue;
            }

            // Use .raw sub-field for filtered text fields
            $filterField = $this->isTextField($fields, $field) ? "{$field}.raw" : $field;
            $filter[] = ['terms' => [$filterField => $values]];
        }

        // Geo distance filter
        if (isset($filters['_geo'])) {
            $geoField = $this->resolveGeoFieldFromFields($fields);
            $filter[] = [
                'geo_distance' => [
                    'distance' => ($filters['_geo']['distance'] ?? '25') . 'km',
                    $geoField => [
                        'lat' => (float) $filters['_geo']['lat'],
                        'lon' => (float) $filters['_geo']['lng'],
                    ],
                    'ignore_unmapped' => true,
                ],
            ];
        }

        $boolQuery = ['filter' => $filter];

        if ($must !== []) {
            $boolQuery['must'] = $must;
        } else {
            $boolQuery['must'] = [['match_all' => new \stdClass()]];
        }

        return ['bool' => $boolQuery];
    }

    /**
     * @param array<string, IndexedField> $fields
     * @return array<string>
     */
    private function getBoostedFields(array $fields): array
    {
        $boosted = [];

        foreach ($fields as $name => $attribute) {
            if ($this->isSearchableField($attribute)) {
                $field = $name;
                if ($attribute->boost !== null && $attribute->boost > 1.0) {
                    $field .= '^' . (int) $attribute->boost;
                }
                $boosted[] = $field;
            }
        }

        return $boosted;
    }

    private function isSearchableField(IndexedField $attribute): bool
    {
        $type = $attribute->type;

        // Text fields and null type (which defaults to text for string properties) are searchable
        return $type === null || $type === 'text';
    }

    /**
     * @param array<string, IndexedField> $fields
     */
    private function isTextField(array $fields, string $fieldName): bool
    {
        if (!isset($fields[$fieldName])) {
            return false;
        }

        $type = $fields[$fieldName]->type;

        return $type === null || $type === 'text';
    }

    /**
     * Resolve the geo_point field path for the given entity class.
     * Venues have geolocation.location, events/festivals have venue.geolocation.location.
     */
    private function resolveGeoField(string $entityClass): string
    {
        $fields = $this->metadataReader->getIndexedFields($entityClass);

        return $this->resolveGeoFieldFromFields($fields);
    }

    /**
     * @param array<string, IndexedField> $fields
     */
    private function resolveGeoFieldFromFields(array $fields): string
    {
        // Check if entity has a direct geolocation field (Venue)
        if (isset($fields['geolocation'])) {
            return 'geolocation.location';
        }

        // Otherwise assume geo is nested in venue relation (Event, Festival)
        return 'venue.geolocation.location';
    }

    private function parseResponse(array $response, int $page, int $perPage): SearchResult
    {
        $total = $response['hits']['total']['value'] ?? 0;
        $hits = $response['hits']['hits'] ?? [];

        $items = [];
        foreach ($hits as $hit) {
            $source = $hit['_source'] ?? [];
            $items[] = new SearchResultItem(
                id: $hit['_id'] ?? '',
                contentType: $source['_content_type'] ?? '',
                locale: $source['_locale'] ?? '',
                score: (float) ($hit['_score'] ?? 0.0),
                source: $source,
            );
        }

        return new SearchResult($items, $total, $page, $perPage);
    }
}
