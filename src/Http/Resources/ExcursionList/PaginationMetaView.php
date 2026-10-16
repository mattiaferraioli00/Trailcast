<?php

namespace App\Http\Resources\ExcursionList;

use App\Dto\PaginatedResult;
use OpenApi\Attributes as OA;

readonly class PaginationMetaView
{
    #[OA\Property(description: 'Current page number, starting from 1.', example: 1)]
    public int $page;

    #[OA\Property(description: 'Maximum number of items per page.', example: 20)]
    public int $per_page;

    #[OA\Property(description: 'Number of matching excursions across all pages.', example: 42)]
    public int $total;

    #[OA\Property(description: 'Number of pages; 1 when there are no results.', example: 3)]
    public int $total_pages;

    /**
     * @param PaginatedResult $result Page of results the metadata refers to.
     */
    public function __construct(PaginatedResult $result)
    {
        $this->page = $result->getPage();
        $this->per_page = $result->getPerPage();
        $this->total = $result->getTotal();
        $this->total_pages = $result->getTotalPages();
    }
}
