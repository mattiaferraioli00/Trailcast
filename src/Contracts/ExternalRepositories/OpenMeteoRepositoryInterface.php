<?php

namespace App\Contracts\ExternalRepositories;

use App\Dto\Excursion\Coordinates;
use App\Dto\Excursion\Forecast;
use App\Exceptions\LocationNotFoundException;
use App\Exceptions\OpenMeteo\GeocodingUnavailableException;
use App\Exceptions\OpenMeteo\OpenMeteoUnavailableException;
use App\Exceptions\OpenMeteo\OpenMeteoUnprocessableException;

interface OpenMeteoRepositoryInterface
{
    /**
     * Resolves the geographic coordinates of a city.
     *
     * @param string $city Name of the city to look up.
     * @param string $country ISO 3166-1 alpha-2 country code (e.g. "IT").
     * @return Coordinates Latitude and longitude of the best match.
     * @throws LocationNotFoundException If the geocoding provider returns no match.
     * @throws GeocodingUnavailableException If the geocoding provider cannot be reached or answers with an error.
     */
    public function geocode(
        string $city,
        string $country,
    ): Coordinates;

    /**
     * Requests the daily forecast from Open-Meteo (cache miss).
     *
     * @param Coordinates $coordinates Location to get the forecast for.
     * @return array<string, Forecast> Forecasts indexed by date (Y-m-d).
     * @throws OpenMeteoUnavailableException
     */
    public function fetchDailyForecast(Coordinates $coordinates): array;
}
