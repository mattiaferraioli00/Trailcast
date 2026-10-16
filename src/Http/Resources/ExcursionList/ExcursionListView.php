<?php

namespace App\Http\Resources\ExcursionList;

use App\Dto\ExcursionForecast;
use App\Dto\PaginatedResult;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

readonly class ExcursionListView
{
    /** @var list<ExcursionItemView> */
    #[OA\Property(
        description: 'Bookable excursions of the current page, ordered by date, then id.',
        type: 'array',
        items: new OA\Items(ref: new Model(type: ExcursionItemView::class)),
    )]
    public array $data;

    public PaginationMetaView $meta;

    /**
     * @param PaginatedResult $result A page of {@see ExcursionForecast} items, with the total number of matches.
     */
    public function __construct(PaginatedResult $result)
    {
        $this->data = array_map(
            fn (ExcursionForecast $item): ExcursionItemView => new ExcursionItemView($item),
            $result->getItems(),
        );
        $this->meta = new PaginationMetaView($result);
    }
}
