<?php

namespace App\Dto\Excursion;

use App\Entity\Excursion;

class ExcursionForecast
{
    private readonly Excursion $excursion;
    private readonly ?Forecast $forecast;

    public function __construct(Excursion $excursion, ?Forecast $forecast)
    {
        $this->excursion = $excursion;
        $this->forecast = $forecast;
    }

    public function getExcursion(): Excursion
    {
        return $this->excursion;
    }

    public function getForecast(): ?Forecast
    {
        return $this->forecast;
    }
}
