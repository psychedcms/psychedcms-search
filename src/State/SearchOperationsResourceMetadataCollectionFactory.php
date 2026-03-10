<?php

declare(strict_types=1);

namespace PsychedCms\Search\State;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use PsychedCms\Search\Action\AutocompleteAction;
use PsychedCms\Search\Action\FacetsAction;
use PsychedCms\Search\Action\SearchAction;
use PsychedCms\Search\Indexing\EntityMetadataReader;

final readonly class SearchOperationsResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    private const SEARCH_OPERATIONS = [
        'search' => [
            'controller' => SearchAction::class,
            'path' => '/search',
            'openapi' => [
                'summary' => 'Search',
                'description' => 'Full-text search with optional filters. Parameters: q, locale, page, perPage.',
            ],
        ],
        'autocomplete' => [
            'controller' => AutocompleteAction::class,
            'path' => '/autocomplete',
            'openapi' => [
                'summary' => 'Autocomplete',
                'description' => 'Get autocomplete suggestions. Parameters: q, locale, size.',
            ],
        ],
        'facets' => [
            'controller' => FacetsAction::class,
            'path' => '/facets',
            'openapi' => [
                'summary' => 'Facets',
                'description' => 'Get facet aggregations for filtering. Parameters: locale.',
            ],
        ],
    ];

    public function __construct(
        private ResourceMetadataCollectionFactoryInterface $decorated,
        private EntityMetadataReader $metadataReader,
    ) {
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $resourceMetadataCollection = $this->decorated->create($resourceClass);

        if (!$this->metadataReader->isIndexed($resourceClass)) {
            return $resourceMetadataCollection;
        }

        $resources = [];
        foreach ($resourceMetadataCollection as $resource) {
            $resources[] = $this->addSearchOperations($resource, $resourceClass);
        }

        return new ResourceMetadataCollection($resourceClass, $resources);
    }

    private function addSearchOperations(ApiResource $resource, string $resourceClass): ApiResource
    {
        $operations = iterator_to_array($resource->getOperations() ?? []);
        $uriTemplate = $this->getBaseUriTemplate($resource, $resourceClass);
        $shortName = $resource->getShortName() ?? $this->getShortName($resourceClass);

        foreach (self::SEARCH_OPERATIONS as $operationName => $config) {
            $operationKey = sprintf('%s_%s', $this->getShortName($resourceClass), $operationName);

            if (isset($operations[$operationKey])) {
                continue;
            }

            $operations[$operationKey] = new Get(
                uriTemplate: $uriTemplate . $config['path'],
                class: $resourceClass,
                shortName: $shortName,
                controller: $config['controller'],
                name: $operationKey,
                read: false,
                deserialize: false,
                validate: false,
                write: false,
                openapi: new \ApiPlatform\OpenApi\Model\Operation(
                    summary: $config['openapi']['summary'],
                    description: $config['openapi']['description'],
                ),
            );
        }

        return $resource->withOperations(new Operations($operations));
    }

    private function getBaseUriTemplate(ApiResource $resource, string $resourceClass): string
    {
        $shortName = $resource->getShortName() ?? $this->getShortName($resourceClass);

        return '/' . strtolower($shortName) . 's';
    }

    private function getShortName(string $resourceClass): string
    {
        $parts = explode('\\', $resourceClass);

        return end($parts);
    }
}
