<?php

namespace App\Http\Resources;

use App\Dto\ExcursionForecast;
use App\Dto\PaginatedResult;
use App\Entity\Excursion;

readonly class ExcursionListView
{
    /** @var PaginatedResult<ExcursionForecast> */
    private PaginatedResult $result;

    public function __construct(PaginatedResult $result)
    {
        $this->result = $result;
    }

    /**
     * Excursions of the current page, in the order returned by the search.
     *
     * @return list<array<string, mixed>> One item per excursion, shaped as described in {@see excursion()}.
     */
    public function getData(): array
    {
        $data = [];
        foreach ($this->result->getItems() as $item) {
            $data[] = $this->excursion($item);
        }

        return $data;
    }

    /**
     * Pagination metadata. "total" is the number of matching excursions across all pages.
     *
     * @return array{page: int, per_page: int, total: int, total_pages: int}
     */
    public function getMeta(): array
    {
        return [
            'page' => $this->result->getPage(),
            'per_page' => $this->result->getPerPage(),
            'total' => $this->result->getTotal(),
            'total_pages' => $this->result->getTotalPages(),
        ];
    }

    /**
     * Maps an excursion and its forecast to a response item. Dates are formatted as YYYY-MM-DD.
     *
     * @param ExcursionForecast $item Excursion with its forecast.
     *
     * @return array
     */
    protected function excursion(ExcursionForecast $item): array
    {
        $excursion = $item->getExcursion();

        return [
            'id' => $excursion->getId(),
            'title' => $excursion->getTitle(),
            'description' => $excursion->getDescription(),
            'city' => $excursion->getCity(),
            'country' => $excursion->getCountry(),
            'date' => $excursion->getDate()->format('Y-m-d'),
            'capacity' => $excursion->getCapacity(),
            'seats_available' => $excursion->getSeatsAvailable(),
            'forecast' => $this->forecast($item),
        ];
    }

    /**
     * Maps the forecast of an excursion. Always present, with the same keys whatever the status:
     * - available: "data" holds the forecast of the excursion day;
     * - not_yet_available: the date is beyond the forecast window, "available_from" is the first day
     *   the forecast will exist;
     * - unavailable: Open-Meteo was unreachable or returned an unexpected response.
     *
     * @param ExcursionForecast $item Excursion with its forecast.
     *
     * @return array
     */
    protected function forecast(ExcursionForecast $item): array
    {
        $forecast = $item->getForecast();

        return [
            'status' => $item->getStatus()->value,
            'available_from' => $item->getAvailableFrom()?->format('Y-m-d'),
            'data' => is_null($forecast) ? null : [
                'weather_code' => $forecast->getWeatherCode(),
                'temperature_max_celsius' => $forecast->getTemperatureMax(),
                'temperature_min_celsius' => $forecast->getTemperatureMin(),
                'precipitation_probability_percent' => $forecast->getPrecipitationProbability(),
            ],
        ];
    }
}
