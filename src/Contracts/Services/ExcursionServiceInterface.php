<?php

namespace App\Contracts\Services;

use App\Dto\Excursion\ExcursionForecast;
use App\Dto\Excursion\PaginatedResult;
use App\Entity\Excursion;
use App\Exceptions\LocationNotFoundException;
use App\Exceptions\OpenMeteo\GeocodingUnavailableException;
use App\Http\Requests\CreateExcursionRequest;
use App\Http\Requests\SearchExcursionRequest;
use Psr\Cache\InvalidArgumentException;

interface ExcursionServiceInterface
{

    /**
     * Creates and persists a new excursion, resolving its coordinates from city and country.
     *
     * @param CreateExcursionRequest $request Validated payload of the excursion to create.
     * @return Excursion The persisted excursion, with its generated id.
     * @throws LocationNotFoundException If no coordinates can be found for the given city and country.
     * @throws GeocodingUnavailableException If the geocoding provider is unavailable.
     */
    public function createExcursion(CreateExcursionRequest $request): Excursion;

    /**
     * Searches the bookable excursions matching the request filters and attaches to each one the weather
     * forecast of its day. A missing forecast (provider unavailable or day out of range) is not an error.
     *
     * @param SearchExcursionRequest $request Validated filters and pagination of the search.
     * @return PaginatedResult A page of {@see ExcursionForecast} items, with the total number of matches.
     * @throws InvalidArgumentException
     */
    public function searchExcursions(SearchExcursionRequest $request): PaginatedResult;
}
