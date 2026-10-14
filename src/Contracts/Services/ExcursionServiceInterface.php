<?php

namespace App\Contracts\Services;

use App\Dto\Excursion\ExcursionForecast;
use App\Dto\Excursion\PaginatedResult;
use App\Entity\Bookings;
use App\Entity\Excursion;
use App\Exceptions\LocationNotFoundException;
use App\Exceptions\NotEnoughSeatsException;
use App\Exceptions\OpenMeteo\GeocodingUnavailableException;
use App\Http\Requests\BookingExcursionRequest;
use App\Http\Requests\CreateExcursionRequest;
use App\Http\Requests\SearchExcursionRequest;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
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

    /**
     * Books seats on an excursion for a customer.
     *
     * Runs in a transaction and locks the excursion row (SELECT ... FOR UPDATE)
     * before checking availability, so concurrent bookings on the same excursion
     * are serialized and an excursion can never sell more seats than its capacity.
     * The excursion is refreshed while acquiring the lock, so the check is always
     * made against the latest committed number of available seats.
     *
     * @param Excursion $excursion Excursion to book, already resolved from the route.
     * @param BookingExcursionRequest $request Validated booking data (customer and number of seats).
     * @return Bookings The persisted booking.
     * @throws NotEnoughSeatsException When fewer seats are available than requested.
     * @throws LockWaitTimeoutException When the lock on the excursion cannot be acquired in time.
     */
    public function bookExcursion(Excursion $excursion, BookingExcursionRequest $request): Bookings;
}
