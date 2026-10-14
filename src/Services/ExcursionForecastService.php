<?php

namespace App\Services;

use App\Contracts\Services\ExcursionForecastServiceInterface;
use App\Dto\Coordinates;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class ExcursionForecastService implements ExcursionForecastServiceInterface
{

    public function __construct(
        protected readonly CacheInterface $cache,
        protected readonly OpenMeteoForecastService $openMeteoForecastService,
    )
    {
    }

    public function getExcursionsForecast(array $excursions)
    {
        $groupByLocation = [];

        foreach ($excursions as $excursion) {
            $key = sprintf('%.2f_%.2f', $excursion->getLatitude(), $excursion->getLongitude());
            $groupByLocation[$key][] = $excursion;
        }

        $forecasts = [];
        foreach ($groupByLocation as $key => $group) {
            $latitude = explode('_', $key)[0];
            $longitude = explode('_', $key)[1];

            $series = $this->openMeteoForecastService->getWeatherData(new Coordinates($latitude, $longitude));

            foreach ($group as $item) {
                $forecasts[$item->getId()] = $series[$item->getDate()->format('Y-m-d')] ?? null;
            }
        }

        return $forecasts;
    }
}
