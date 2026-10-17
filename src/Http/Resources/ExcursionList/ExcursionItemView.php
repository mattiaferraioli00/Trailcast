<?php

namespace App\Http\Resources\ExcursionList;

use App\Dto\ExcursionForecast;
use App\Http\Resources\ExcursionView;

readonly class ExcursionItemView extends ExcursionView
{
    public ForecastView $forecast;

    public function __construct(ExcursionForecast $item)
    {
        parent::__construct($item->getExcursion());

        $this->forecast = new ForecastView($item);
    }
}

