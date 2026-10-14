<?php

namespace App\Contracts\Services;

use App\Dto\PaginatedResult;
use App\Entity\Excursion;
use App\Exceptions\LocationNotFoundException;
use App\Exceptions\OpenMeteo\GeocodingUnavailableException;
use App\Http\Requests\CreateExcursionRequest;
use App\Http\Requests\SearchExcursionRequest;

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

    public function searchExcursions(SearchExcursionRequest $request): PaginatedResult;
}
