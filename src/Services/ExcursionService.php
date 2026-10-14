<?php

namespace App\Services;

use App\Contracts\Services\ExcursionServiceInterface;
use App\Contracts\Services\OpenMeteoServiceInterface;
use App\Entity\Excursion;
use App\Http\Requests\CreateExcursionRequest;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ExcursionService implements ExcursionServiceInterface
{

    public function __construct(
        protected readonly OpenMeteoServiceInterface $openMeteoService,
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
}
