<?php

namespace App\Http\Resources\ExcursionList;

use App\Dto\PaginatedResult;
use OpenApi\Attributes as OA;

readonly class PaginationMetaView
{
    #[OA\Property(description: 'Current page number, starting from 1.', example: 1)]
    public int $page;

    #[OA\Property(description: 'Maximum number of items per page.', example: 20)]
    public int $perPage;

    #[OA\Property(description: 'Number of matching excursions across all pages.', example: 42)]
    public int $total;

    #[OA\Property(description: 'Number of pages; 1 when there are no results.', example: 3)]
    public int $totalPages;

    public function __construct(PaginatedResult $result)
    {
        $this->page = $result->getPage();
        $this->perPage = $result->getPerPage();
        $this->total = $result->getTotal();
        $this->totalPages = $result->getTotalPages();
    }
}
