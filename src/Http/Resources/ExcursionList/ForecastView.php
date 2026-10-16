<?php

namespace App\Http\Resources\ExcursionList;

use App\Constants\ForecastStatus;
use App\Dto\ExcursionForecast;
use OpenApi\Attributes as OA;

/**
 * Forecast of an excursion. Always present, with the same keys whatever the status.
 */
readonly class ForecastView
{
    #[OA\Property(description: 'available: "data" holds the forecast of the excursion day. '
        . 'not_yet_available: the date is beyond the 16-day forecast window, see "available_from". '
        . 'unavailable: Open-Meteo was unreachable or returned an unexpected response.')]
    public ForecastStatus $status;

    #[OA\Property(
        description: 'First day the forecast of the excursion day will be available. Set only when status is not_yet_available.',
        format: 'date',
        example: '2026-07-28',
    )]
    public ?string $available_from;

    #[OA\Property(description: 'Forecast of the excursion day. Set only when status is available.')]
    public ?WeatherView $data;

    /**
     * @param ExcursionForecast $item Excursion with the forecast of its day and the forecast status.
     */
    public function __construct(ExcursionForecast $item)
    {
        $forecast = $item->getForecast();

        $this->status = $item->getStatus();
        $this->available_from = $item->getAvailableFrom()?->format('Y-m-d');
        $this->data = is_null($forecast) ? null : new WeatherView($forecast);
    }
}

