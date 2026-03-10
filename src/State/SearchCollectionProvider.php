<?php

declare(strict_types=1);

namespace PsychedCms\Search\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use PsychedCms\Search\Client\ElasticsearchClientInterface;
use PsychedCms\Search\Index\IndexNameResolver;
use PsychedCms\Search\Indexing\EntityMetadataReader;
use PsychedCms\Search\Search\SearchServiceInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class SearchCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly ProviderInterface $decorated,
        private readonly EntityMetadataReader $metadataReader,
        private readonly SearchServiceInterface $searchService,
        private readonly ElasticsearchClientInterface $esClient,
        private readonly IndexNameResolver $nameResolver,
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $resourceClass = $operation->getClass();

        if ($resourceClass === null || !$this->metadataReader->isIndexed($resourceClass)) {
            return $this->decorated->provide($operation, $uriVariables, $context);
        }

        if (!$this->shouldUseElasticsearch($resourceClass)) {
            return $this->decorated->provide($operation, $uriVariables, $context);
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return $this->decorated->provide($operation, $uriVariables, $context);
        }

        $clientType = $request->headers->get('X-Client-Type', 'public');
        $searchQuery = $request->query->get('search', '');

        // CQRS logic:
        // - Public requests always go to ES
        // - Admin with search param goes to ES
        // - Admin without search goes to Doctrine
        if ($clientType === 'admin' && $searchQuery === '') {
            return $this->decorated->provide($operation, $uriVariables, $context);
        }

        // Check ES availability
        if (!$this->esClient->isAvailable()) {
            return $this->decorated->provide($operation, $uriVariables, $context);
        }

        $locale = $request->query->get('locale', $request->getLocale());
        $page = max(1, (int) $request->query->get('page', '1'));
        $perPage = min(100, max(1, (int) $request->query->get('perPage', '20')));
        $query = $searchQuery !== '' ? $searchQuery : ($request->query->get('q', '') ?: '');

        $searchResult = $this->searchService->search(
            $resourceClass,
            $query,
            $locale,
            $page,
            $perPage
        );

        // Hydrate from Doctrine by IDs to get full entities
        return $this->hydrateFromIds($resourceClass, $searchResult->items);
    }

    private function shouldUseElasticsearch(string $resourceClass): bool
    {
        try {
            $this->nameResolver->resolve($resourceClass);

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    /**
     * @return array<object>
     */
    private function hydrateFromIds(string $resourceClass, array $items): array
    {
        if ($items === []) {
            return [];
        }

        // Extract IDs from search results
        $ids = [];
        foreach ($items as $item) {
            // Parse the document ID: {type}_{id}_{locale}
            $parts = explode('_', $item->id);
            if (\count($parts) >= 2) {
                $ids[] = $parts[\count($parts) - 2]; // second to last is the entity ID
            }
        }

        $ids = array_unique($ids);

        if ($ids === []) {
            return [];
        }

        $repository = $this->entityManager->getRepository($resourceClass);
        $entities = $repository->findBy(['id' => $ids]);

        // Preserve search result ordering
        $entityMap = [];
        foreach ($entities as $entity) {
            if (method_exists($entity, 'getId')) {
                $entityMap[(string) $entity->getId()] = $entity;
            }
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($entityMap[(string) $id])) {
                $ordered[] = $entityMap[(string) $id];
            }
        }

        return $ordered;
    }
}
