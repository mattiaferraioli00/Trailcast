<?php

namespace App\Dto;

readonly class PaginatedResult
{
    protected array $items;
    protected int $total;
    protected int $page;
    protected int $perPage;


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

    /**
     * @return array Items of the current page, at most perPage.
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @return int Number of matching items across all pages.
     */
    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * @return int Current page number, starting from 1.
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * @return int Maximum number of items per page.
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * @return int Number of pages; 1 when there are no results, so an empty search still has one (empty) page.
     */
    public function getTotalPages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }
}
