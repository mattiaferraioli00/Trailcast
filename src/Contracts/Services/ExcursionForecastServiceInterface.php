<?php

namespace App\Contracts\Services;

interface ExcursionForecastServiceInterface
{
    public function getExcursionsForecast(array $excursions);
}
