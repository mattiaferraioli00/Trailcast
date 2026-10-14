<?php

namespace App\Services;

use App\Contracts\Services\ExcursionForecastServiceInterface;
use App\Contracts\Services\ExcursionServiceInterface;
use App\Contracts\Services\OpenMeteoServiceInterface;
use App\Dto\Coordinates;
use App\Dto\ExcursionForecast;
use App\Dto\PaginatedResult;
use App\Entity\Excursion;
use App\Http\Requests\CreateExcursionRequest;
use App\Http\Requests\SearchExcursionRequest;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ExcursionService implements ExcursionServiceInterface
{

    public function __construct(
        protected readonly ExcursionForecastServiceInterface $forecastService,
        protected readonly OpenMeteoServiceInterface $openMeteoService,
        protected readonly OpenMeteoForecastService $openMeteoForecastService,
        protected readonly EntityManagerInterface $entityManager,
        protected readonly LoggerInterface $logger,
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function createExcursion(CreateExcursionRequest $request): Excursion
    {
        $this->logger->info('Locating coordinates for new excursion.', [
            'city' => $request->getCity(),
            'country' => $request->getCountry(),
        ]);

        $coordinates = $this->openMeteoService->locateCoordinates($request->getCity(), $request->getCountry());

        $this->logger->debug('Coordinates located.', [
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

        $this->logger->info('Excursion persisted.', ['excursion_id' => $excursion->getId()]);

        return $excursion;
    }

    public function searchExcursions(SearchExcursionRequest $request): PaginatedResult
    {
        $page = $this->entityManager->getRepository(Excursion::class)->findBookable($request, (new \DateTimeImmutable('now'))->setTime(0, 0));
        $seriesByLocation = [];
        $items = [];

        foreach ($page->getItems() as $excursion) {
            $location = sprintf('%.2f_%.2f', $excursion->getLatitude(), $excursion->getLongitude());

            $seriesByLocation[$location] ??= $this->openMeteoForecastService->getWeatherData(
                new Coordinates($excursion->getLatitude(), $excursion->getLongitude())
            );

            $items[] = new ExcursionForecast(
                $excursion,
                $seriesByLocation[$location][$excursion->getDate()->format('Y-m-d')] ?? null,
            );
        }

        return new PaginatedResult(
            $items,
            $page->getTotalCount(),
            $request->getPage(),
            $request->getPerPage(),
        );
    }
}
