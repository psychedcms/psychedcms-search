<?php

declare(strict_types=1);

namespace PsychedCms\Search\Exception;

use PsychedCms\Elasticsearch\Exception\SearchExceptionInterface;

final class InvalidSearchQueryException extends \InvalidArgumentException implements SearchExceptionInterface
{
    public function __construct(string $reason, ?\Throwable $previous = null)
    {
        parent::__construct(
            sprintf('Invalid search query: %s', $reason),
            400,
            $previous
        );
    }
}
