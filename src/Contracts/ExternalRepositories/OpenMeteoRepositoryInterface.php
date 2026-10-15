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
     * Resolves a city name to coordinates using the Open-Meteo Geocoding API.
     *
     * Open-Meteo matches names by prefix, so up to 10 results are requested and only
     * one whose name is identical to the requested city is accepted (case-insensitive,
     * accent-sensitive). When several places share the same name, the first one returned
     * by Open-Meteo is used. Colloquial or abbreviated names and localities not indexed
     * by Open-Meteo (e.g. many hamlets) are not resolved.
     *
     * @param string $city    City name, in Italian (results are localized with language=it).
     * @param string $country ISO 3166-1 alpha-2 country code used to filter results.
     *
     * @return Coordinates Coordinates of the matched location.
     *
     * @throws GeocodingUnavailableException When the Open-Meteo request fails.
     * @throws LocationNotFoundException     When no result matches the city name exactly.
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
