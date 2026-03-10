<?php

declare(strict_types=1);

namespace PsychedCms\Search\Action;

use PsychedCms\Search\Search\AutocompleteServiceInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
final readonly class AutocompleteAction
{
    public function __construct(
        private AutocompleteServiceInterface $autocompleteService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $resourceClass = $request->attributes->get('_api_resource_class');
        $prefix = $request->query->get('q', '');
        $locale = $request->query->get('locale', $request->getLocale());
        $size = min(50, max(1, (int) $request->query->get('size', '10')));

        $suggestions = $this->autocompleteService->suggest(
            $resourceClass,
            $prefix,
            $locale,
            $size
        );

        return new JsonResponse($suggestions);
    }
}
