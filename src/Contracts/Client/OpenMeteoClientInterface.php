<?php

namespace App\Contracts\Client;

use App\Dto\Coordinates;
use App\Dto\Forecast;
use App\Exception\LocationNotFoundException;
use App\Exception\OpenMeteo\GeocodingUnavailableException;
use App\Exception\OpenMeteo\OpenMeteoUnavailableException;

interface OpenMeteoClientInterface
{
    /**
     * Resolves a city name to coordinates using the Open-Meteo Geocoding API.
     *
     * Results are filtered by country and localized in Italian. Open-Meteo matches names by prefix,
     * so up to 10 results are requested and only one whose name is identical to the requested city
     * is accepted (case-insensitive, accent-sensitive). When several places share the same name,
     * the first one returned by Open-Meteo is used. Colloquial or abbreviated names and localities
     * not indexed by Open-Meteo (e.g. many hamlets) are not resolved.
     *
     * @param string $city    City name, in Italian.
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
     * Fetches the daily forecast of a location from the Open-Meteo Forecast API.
     *
     * The whole forecast window (today included) is requested at once, in the location's own
     * timezone, so the dates match the local date of the excursions. Values missing from the
     * response are returned as null.
     *
     * @param Coordinates $coordinates Location to get the forecast for.
     *
     * @return array<string, Forecast> Forecasts indexed by local date (Y-m-d), starting from today.
     *
     * @throws OpenMeteoUnavailableException When the request fails or the response has an unexpected shape.
     */
    public function fetchDailyForecast(Coordinates $coordinates): array;
}
