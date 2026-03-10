<?php

declare(strict_types=1);

namespace PsychedCms\Search\Tests\Fixtures;

class NonIndexedEntity
{
    private ?int $id = null;
    private ?string $name = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }
}
