<?php

namespace App\Http\Views;

use App\Dto\ExcursionForecast;
use App\Dto\Forecast;
use App\Dto\PaginatedResult;
use App\Entity\Excursion;

class ExcursionListView
{
    /** @var PaginatedResult<ExcursionForecast> */
    private readonly PaginatedResult $result;

    /**
     * @param PaginatedResult<ExcursionForecast> $result
     */
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
            $data[] = $this->excursion($item->getExcursion(), $item->getForecast());
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
    protected function excursion(Excursion $excursion, ?Forecast $forecast): array
    {
        return [
            'id' => (string) $excursion->getId(),
            'title' => $excursion->getTitle(),
            'description' => $excursion->getDescription(),
            'city' => $excursion->getCity(),
            'country' => $excursion->getCountry(),
            'date' => $excursion->getDate()->format('Y-m-d'),
            'capacity' => $excursion->getCapacity(),
            'seats_available' => $excursion->getSeatsAvailable(),
            'forecast' => $this->forecast($forecast),
        ];
    }

    /**
     * A missing forecast (date outside the 16-day window or weather provider
     * unavailable) is rendered as null.
     *
     * @return array<string, int|float|null>|null
     */
    protected function forecast(?Forecast $forecast): ?array
    {
        if (is_null($forecast)) {
            return null;
        }

        return [
            'temperature_max_celsius' => $forecast->getTemperatureMax(),
            'temperature_min_celsius' => $forecast->getTemperatureMin(),
            'precipitation_probability_percent' => $forecast->getPrecipitationProbability(),
        ];
    }
}
