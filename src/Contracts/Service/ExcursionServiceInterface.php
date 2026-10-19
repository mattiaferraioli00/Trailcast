<?php

namespace App\Contracts\Service;

use App\Dto\ExcursionForecast;
use App\Dto\PaginatedResult;
use App\Entity\Bookings;
use App\Entity\Excursion;
use App\Exception\ExcursionBusyException;
use App\Exception\LocationNotFoundException;
use App\Exception\NotEnoughSeatsException;
use App\Exception\OpenMeteo\GeocodingUnavailableException;
use App\Http\Requests\BookingExcursionRequest;
use App\Http\Requests\CreateExcursionRequest;
use App\Http\Requests\SearchExcursionRequest;
use Psr\Cache\InvalidArgumentException;

interface ExcursionServiceInterface
{
    /**
     * Creates and persists a new excursion, resolving its coordinates from city and country.
     *
     * The city is geocoded once, at creation time, and the coordinates are stored with the excursion,
     * so reading excursions never calls the geocoding provider.
     *
     * @param CreateExcursionRequest $request Validated payload of the excursion to create.
     *
     * @return Excursion The persisted excursion, with its generated id.
     *
     * @throws LocationNotFoundException     When no location matches the city in the given country.
     * @throws GeocodingUnavailableException When the geocoding provider cannot be reached.
     */
    public function createExcursion(CreateExcursionRequest $request): Excursion;

    /**
     * Searches the bookable excursions matching the request filters and attaches to each one the
     * weather forecast of its day.
     *
     * An excursion is bookable when its date is from today on, and it has at least one seat available.
     * Results are ordered by date, then id.
     *
     * The forecast series is fetched once per location (coordinates rounded to two decimals) and cached.
     * A missing forecast is not an error: each item carries a status (available, not_yet_available,
     * unavailable), and a weather provider failure never fails the search.
     *
     * @param SearchExcursionRequest $request Validated filters and pagination of the search.
     *
     * @return PaginatedResult A page of {@see ExcursionForecast} items, with the total number of matches.
     *
     * @throws InvalidArgumentException When a forecast cache key is invalid.
     */
    public function searchExcursions(SearchExcursionRequest $request): PaginatedResult;

    /**
     * Books seats on an excursion for a customer.
     *
     * Runs in a transaction and locks the excursion row (SELECT ... FOR UPDATE) before checking
     * availability, so concurrent bookings on the same excursion are serialized and an excursion can
     * never sell more seats than its capacity. The excursion is refreshed while acquiring the lock, so
     * the check is always made against the latest committed number of available seats.
     *
     * @param Excursion               $excursion Excursion to book, already resolved from the route.
     * @param BookingExcursionRequest $request   Validated booking data (customer and number of seats).
     *
     * @return Bookings The persisted booking.
     *
     * @throws NotEnoughSeatsException   When the excursion is in the past or fewer seats are available than requested.
     * @throws ExcursionBusyException    When the lock on the excursion cannot be acquired in time (lock wait
     *                                   timeout or deadlock); the booking is rolled back.
     */
    public function bookExcursion(Excursion $excursion, BookingExcursionRequest $request): Bookings;
}
