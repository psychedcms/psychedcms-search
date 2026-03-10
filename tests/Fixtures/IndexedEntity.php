<?php

declare(strict_types=1);

namespace PsychedCms\Search\Tests\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use PsychedCms\Search\Attribute\Indexed;
use PsychedCms\Search\Attribute\IndexedField;

#[Indexed]
class IndexedEntity
{
    private ?int $id = null;

    #[IndexedField(boost: 3.0, autocomplete: true)]
    private ?string $title = null;

    #[IndexedField]
    private ?string $description = null;

    #[IndexedField(type: 'date', filterable: true, sortable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[IndexedField(type: 'geo_point')]
    private ?array $location = null;

    #[IndexedField(facetable: true, filterable: true)]
    private Collection $tags;

    private ?string $notIndexed = null;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getLocation(): ?array
    {
        return $this->location;
    }

    public function setLocation(?array $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getTags(): Collection
    {
        return $this->tags;
    }
}
