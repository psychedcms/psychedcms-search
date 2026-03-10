<?php

declare(strict_types=1);

namespace PsychedCms\Search\Action;

use PsychedCms\Search\Search\FacetServiceInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
final readonly class FacetsAction
{
    public function __construct(
        private FacetServiceInterface $facetService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $resourceClass = $request->attributes->get('_api_resource_class');
        $locale = $request->query->get('locale', $request->getLocale());

        $facets = $this->facetService->getFacets($resourceClass, $locale);

        return new JsonResponse(array_map(fn ($facet) => [
            'fieldName' => $facet->fieldName,
            'type' => $facet->type,
            'buckets' => $facet->buckets,
        ], $facets));
    }
}
