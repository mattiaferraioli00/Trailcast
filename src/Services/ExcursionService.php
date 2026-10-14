<?php

namespace App\Services;

use App\Contracts\ExternalRepositories\OpenMeteoRepositoryInterface;
use App\Contracts\Services\ExcursionServiceInterface;
use App\Dto\Excursion\Coordinates;
use App\Dto\Excursion\ExcursionForecast;
use App\Dto\Excursion\PaginatedResult;
use App\Entity\Excursion;
use App\Http\Requests\CreateExcursionRequest;
use App\Http\Requests\SearchExcursionRequest;
use App\Services\Cache\CachedForecastService;
use App\Traits\LoggerTrait;
use Doctrine\ORM\EntityManagerInterface;

class ExcursionService implements ExcursionServiceInterface
{
    use LoggerTrait;

    public function __construct(
        protected readonly OpenMeteoRepositoryInterface $openMeteoRepository,
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
        $this->logger->info('Locating coordinates for new excursion.', [
            'city' => $request->getCity(),
            'country' => $request->getCountry(),
        ]);

        $coordinates = $this->openMeteoRepository->geocode($request->getCity(), $request->getCountry());

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

    /**
     * @inheritDoc
     */
    public function searchExcursions(SearchExcursionRequest $request): PaginatedResult
    {
        $this->logger->debug('Loading bookable excursions.', [
            'page' => $request->getPage(),
            'per_page' => $request->getPerPage(),
        ]);

        $page = $this->entityManager->getRepository(Excursion::class)->findBookable($request, (new \DateTimeImmutable('now'))->setTime(0, 0));
        $seriesByLocation = [];
        $items = [];

        foreach ($page->getItems() as $excursion) {
            $location = sprintf('%.2f_%.2f', $excursion->getLatitude(), $excursion->getLongitude());

            $seriesByLocation[$location] ??= $this->cachedForecastService->getDailyForecast(
                new Coordinates($excursion->getLatitude(), $excursion->getLongitude())
            );

            $items[] = new ExcursionForecast(
                $excursion,
                $seriesByLocation[$location][$excursion->getDate()->format('Y-m-d')] ?? null,
            );
        }

        $this->logger->info('Excursion search completed.', [
            'excursions' => count($items),
            'forecast_locations' => count($seriesByLocation),
            'total' => $page->getTotalCount(),
        ]);

        return new PaginatedResult(
            $items,
            $page->getTotalCount(),
            $request->getPage(),
            $request->getPerPage(),
        );
    }
}
