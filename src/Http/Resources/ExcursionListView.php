<?php

namespace App\Http\Resources;

use App\Dto\Excursion\ExcursionForecast;
use App\Dto\Excursion\Forecast;
use App\Dto\Excursion\PaginatedResult;
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
     * @return list<array<string, mixed>>
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
     * @return array<string, int>
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
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function jsonSerialize(): array
    {
        return [
            'data' => $this->getData(),
            'meta' => $this->getMeta(),
        ];
    }

    /**
     * @return array<string, mixed>
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
     * Always present, with a stable shape:
     * - available: data holds the daily forecast
     * - not_yet_available: date beyond the 16-day window, available_from says when it will be
     * - unavailable: weather provider unreachable
     *
     * @return array{status: string, available_from: string|null, data: array<string, int|float|null>|null}
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
