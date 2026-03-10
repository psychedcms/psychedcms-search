<?php

declare(strict_types=1);

namespace PsychedCms\Search\Tests\Fixtures;

class TagFixture
{
    public function __construct(
        private readonly int $id,
        private readonly string $name,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
