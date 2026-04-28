<?php

namespace Module\Post\DTO;

use App\User;

class PostQueryParams
{
    public ?int $userId = null;
    public ?User $viewer = null;
    public ?string $mediaType = null;
    public ?string $sortBy = null;
    public ?string $sortOrder = null;
    public ?string $searchTerm = null;
    public int $page = 1;
    public ?int $perPage = null;

    public static function make(): self
    {
        return new self();
    }

    public function forUser(int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function viewedBy(?User $viewer): self
    {
        $this->viewer = $viewer;

        return $this;
    }

    public function withMediaType(?string $mediaType): self
    {
        $this->mediaType = $mediaType;

        return $this;
    }

    public function withSortBy(?string $sortBy): self
    {
        $this->sortBy = $sortBy;

        return $this;
    }

    public function withSortOrder(?string $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function withSearch(?string $searchTerm): self
    {
        $this->searchTerm = $searchTerm;

        return $this;
    }

    public function page(int $page): self
    {
        $this->page = $page;

        return $this;
    }

    public function perPage(int $perPage): self
    {
        $this->perPage = $perPage;

        return $this;
    }
}
