<?php

namespace App\Http\Resources\ExcursionList;

use App\Dto\Forecast;
use OpenApi\Attributes as OA;

/**
 * Weather values of the excursion day. Each value is null when Open-Meteo does not provide it.
 */
readonly class WeatherView
{
    #[OA\Property(
        description: 'Most severe weather condition of the day, as a WMO weather interpretation code '
        . '(e.g. 0 clear sky, 61 slight rain, 95 thunderstorm).',
        example: 61,
    )]
    public ?int $weatherCode;

    #[OA\Property(description: 'Maximum air temperature of the day at 2 m above ground, in °C.', example: 16.4)]
    public ?float $temperatureMaxCelsius;

    #[OA\Property(description: 'Minimum air temperature of the day at 2 m above ground, in °C.', example: 9.8)]
    public ?float $temperatureMinCelsius;

    #[OA\Property(
        description: 'Highest hourly probability of precipitation during the day, in percent.',
        example: 70,
        maximum: 100,
        minimum: 0,
    )]
    public ?int $precipitationProbabilityPercent;

    public function __construct(Forecast $forecast)
    {
        $this->weatherCode = $forecast->getWeatherCode();
        $this->temperatureMaxCelsius = $forecast->getTemperatureMax();
        $this->temperatureMinCelsius = $forecast->getTemperatureMin();
        $this->precipitationProbabilityPercent = $forecast->getPrecipitationProbability();
    }
}
