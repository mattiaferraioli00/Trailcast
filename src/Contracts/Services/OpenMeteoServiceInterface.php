<?php

namespace App\Contracts\Services;

use App\Dto\Coordinates;
use App\Exceptions\LocationNotFoundException;
use App\Exceptions\OpenMeteo\GeocodingUnavailableException;

interface OpenMeteoServiceInterface
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
    public function locateCoordinates(string $city, string $country): Coordinates;
}
