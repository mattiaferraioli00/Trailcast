<?php

namespace App\Service;

use App\Contracts\Client\OpenMeteoClientInterface;
use App\Contracts\Service\ExcursionServiceInterface;
use App\Dto\Coordinates;
use App\Dto\ExcursionForecast;
use App\Dto\Forecast;
use App\Dto\PaginatedResult;
use App\Entity\Bookings;
use App\Entity\Excursion;
use App\Exception\NotEnoughSeatsException;
use App\Exception\OpenMeteo\OpenMeteoUnavailableException;
use App\Client\OpenMeteoClient;
use App\Http\Requests\BookingExcursionRequest;
use App\Http\Requests\CreateExcursionRequest;
use App\Http\Requests\SearchExcursionRequest;
use App\Service\Cache\CachedForecastService;
use App\Traits\LoggerTrait;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

class ExcursionService implements ExcursionServiceInterface
{
    use LoggerTrait;

    public function __construct(
        protected readonly OpenMeteoClientInterface $openMeteoClient,
        protected readonly CachedForecastService $cachedForecastService,
        protected readonly EntityManagerInterface $entityManager,
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function createExcursion(CreateExcursionRequest $request): Excursion
    {
        $this->logger->debug('Geocoding excursion city.', [
            'city' => $request->getCity(),
            'country' => $request->getCountry(),
        ]);

        $coordinates = $this->openMeteoClient->geocode($request->getCity(), $request->getCountry());

        $this->logger->debug('Excursion city geocoded.', [
            'city' => $request->getCity(),
            'latitude' => $coordinates->getLatitude(),
            'longitude' => $coordinates->getLongitude(),
        ]);

        $excursion = new Excursion(
            title: $request->getTitle(),
            description: $request->getDescription(),
            city: $request->getCity(),
            country: $request->getCountry(),
            date: $request->getDate(),
            capacity: $request->getCapacity(),
            latitude: $coordinates->getLatitude(),
            longitude: $coordinates->getLongitude(),
        );

        $this->entityManager->persist($excursion);
        $this->entityManager->flush();

        $this->logger->info('Excursion created.', [
            'excursion_id' => $excursion->getId(),
            'city' => $excursion->getCity(),
            'country' => $excursion->getCountry(),
            'date' => $excursion->getDate()->format('Y-m-d'),
            'capacity' => $excursion->getCapacity(),
        ]);

        return $excursion;
    }

    /**
     * @inheritDoc
     */
    public function searchExcursions(SearchExcursionRequest $request): PaginatedResult
    {
        $page = $this->entityManager->getRepository(Excursion::class)->findBookable($request, new DateTimeImmutable('today'));
        $seriesByLocation = [];
        $items = [];

        foreach ($page->getItems() as $excursion) {
            $coordinates = new Coordinates($excursion->getLatitude(), $excursion->getLongitude());
            $location = $coordinates->roundedKey();

            if (!array_key_exists($location, $seriesByLocation)) {
                try {
                    $seriesByLocation[$location] = $this->cachedForecastService->getDailyForecast($coordinates);
                } catch (OpenMeteoUnavailableException $e) {
                    $this->logger->warning('Forecast unavailable, excursions returned without it.', [
                        'location' => $location,
                        'exception' => $e,
                    ]);
                    $seriesByLocation[$location] = null;
                }
            }

            $items[] = $this->buildExcursionForecast($excursion, $seriesByLocation[$location]);
        }

        $this->logger->debug('Excursion search completed.', [
            'returned' => count($items),
            'total' => $page->getTotalCount(),
            'forecast_locations' => count($seriesByLocation),
            'forecast_unavailable_locations' => count(array_filter($seriesByLocation, 'is_null')),
        ]);

        return new PaginatedResult(
            $items,
            $page->getTotalCount(),
            $request->getPage(),
            $request->getPerPage(),
        );
    }

    /**
     * Builds the forecast entry of an excursion from the daily series of its location.
     *
     * Whether a date is beyond the forecast window is decided from the last day of the series
     * returned by Open-Meteo, which is computed in the location's timezone.
     *
     * @param Excursion                    $excursion Excursion to attach the forecast to.
     * @param array<string, Forecast>|null $series    Daily forecasts indexed by date (Y-m-d), null if Open-Meteo was unreachable.
     *
     * @return ExcursionForecast The excursion with its forecast and forecast status.
     */
    protected function buildExcursionForecast(Excursion $excursion, ?array $series): ExcursionForecast
    {
        $date = $excursion->getDate()->format('Y-m-d');

        if (is_null($series)) {
            return ExcursionForecast::unavailable($excursion);
        }

        if (isset($series[$date])) {
            return ExcursionForecast::available($excursion, $series[$date]);
        }

        if (!empty($series) && $date > array_key_last($series)) {
            return ExcursionForecast::notYetAvailable(
                $excursion,
                DateTimeImmutable::createFromInterface($excursion->getDate())
                    ->modify(sprintf('-%d days', OpenMeteoClient::FORECAST_DAYS - 1)),
            );
        }

        return ExcursionForecast::unavailable($excursion);
    }

    /**
     * @inheritDoc
     */
    public function bookExcursion(Excursion $excursion, BookingExcursionRequest $request): Bookings
    {
        $booking = $this->entityManager->wrapInTransaction(function () use ($excursion, $request): Bookings {
            $this->entityManager->refresh($excursion, LockMode::PESSIMISTIC_WRITE);

            if (!$excursion->isBookable($request->getSeats())) {
                $this->logger->info('Booking rejected.', [
                    'excursion_id' => $excursion->getId(),
                    'excursion_date' => $excursion->getDate()->format('Y-m-d'),
                    'requested_seats' => $request->getSeats(),
                    'seats_available' => $excursion->getSeatsAvailable(),
                ]);

                throw new NotEnoughSeatsException(
                    message: "Not enough seats available for excursion with ID {$excursion->getId()}",
                    excursion: $excursion,
                    seats: $request->getSeats()
                );
            }

            $excursion->reserveSeats($request->getSeats());

            $booking = new Bookings(
                name: $request->getCustomerName(),
                email: $request->getCustomerEmail(),
                seats: $request->getSeats(),
                excursion: $excursion
            );

            $this->entityManager->persist($booking);
            $this->entityManager->flush();

            return $booking;
        });

        $this->logger->info('Booking created.', [
            'booking_id' => $booking->getId(),
            'excursion_id' => $excursion->getId(),
            'seats' => $booking->getSeats(),
            'seats_available' => $excursion->getSeatsAvailable(),
        ]);

        return $booking;
    }
}
