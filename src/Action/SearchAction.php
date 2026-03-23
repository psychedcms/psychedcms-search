<?php

declare(strict_types=1);

namespace PsychedCms\Search\Action;

use PsychedCms\Search\Search\FacetServiceInterface;
use PsychedCms\Search\Search\SearchServiceInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
final readonly class SearchAction
{
    public function __construct(
        private SearchServiceInterface $searchService,
        private FacetServiceInterface $facetService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $resourceClass = $request->attributes->get('_api_resource_class');
        $query = $request->query->get('q', '');
        $locale = $request->query->get('locale', $request->getLocale());
        $page = max(1, (int) $request->query->get('page', '1'));
        $perPage = min(100, max(1, (int) $request->query->get('perPage', '20')));
        $filters = $request->query->all('filters');

        $result = $this->searchService->search(
            $resourceClass,
            $query,
            $locale,
            $page,
            $perPage,
            $filters,
        );

        $facets = $this->facetService->getFacets($resourceClass, $locale);

        return new JsonResponse([
            'items' => array_map(fn ($item) => [
                'id' => $item->id,
                'contentType' => $item->contentType,
                'locale' => $item->locale,
                'score' => $item->score,
                'source' => $item->source,
            ], $result->items),
            'total' => $result->total,
            'page' => $result->page,
            'perPage' => $result->perPage,
            'facets' => array_map(fn ($facet) => [
                'fieldName' => $facet->fieldName,
                'type' => $facet->type,
                'buckets' => $facet->buckets,
            ], $facets),
        ]);
    }
}
