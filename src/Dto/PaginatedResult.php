<?php

namespace App\Dto;

class PaginatedResult
{
    private readonly array $items;
    private readonly int $total;
    private readonly int $page;
    private readonly int $perPage;


    public function __construct(
        array $items,
        int $total, int $page,
        int $perPage)
    {
        $this->items = $items;
        $this->total = $total;
        $this->page = $page;
        $this->perPage = $perPage;
    }


    public function getItems(): array
    {
        return $this->items;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getTotalPages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }
}
