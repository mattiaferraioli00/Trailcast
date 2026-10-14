<?php

namespace App\Tests\Integration\Services\ExcursionService;

use App\Contracts\Services\ExcursionServiceInterface;
use App\Contracts\Services\OpenMeteoServiceInterface;
use App\Dto\Coordinates;
use App\Entity\Excursion;
use App\Exceptions\LocationNotFoundException;
use App\Exceptions\OpenMeteo\GeocodingUnavailableException;
use App\Http\Requests\CreateExcursionRequest;
use App\Services\ExcursionService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Throwable;

#[CoversMethod(ExcursionService::class, 'createExcursion')]
class CreateExcursionTest extends KernelTestCase
{
    protected OpenMeteoServiceInterface&MockObject $openMeteo;

    protected EntityManagerInterface $entityManager;

    protected ExcursionServiceInterface $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->openMeteo = $this->createMock(OpenMeteoServiceInterface::class);
        $container->set(OpenMeteoServiceInterface::class, $this->openMeteo);

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->service = $container->get(ExcursionServiceInterface::class);
    }

    /**
     * Given a valid request and a geocoding service that locates the city,
     * when the excursion is created,
     * then the excursion is stored in the database with the request data and the located coordinates.
     */
    #[Test]
    public function stores_the_excursion_with_the_located_coordinates(): void
    {
        $request = $this->createRequest();
        $this->openMeteo->expects($this->once())
            ->method('locateCoordinates')
            ->with('Catania', 'IT')
            ->willReturn(new Coordinates(37.49223, 15.07041));

        $created = $this->service->createExcursion($request);

        $this->entityManager->clear();
        $stored = $this->entityManager->getRepository(Excursion::class)->find($created->getId());

        $this->assertNotNull($stored);
        $this->assertSame('Etna sunrise', $stored->getTitle());
        $this->assertSame('Guided trek to the summit craters.', $stored->getDescription());
        $this->assertSame('Catania', $stored->getCity());
        $this->assertSame('IT', $stored->getCountry());
        $this->assertSame($request->getDate()->format('Y-m-d H:i:s'), $stored->getDate()->format('Y-m-d H:i:s'));
        $this->assertSame(10, $stored->getCapacity());
        $this->assertSame(37.49223, $stored->getLatitude());
        $this->assertSame(15.07041, $stored->getLongitude());
    }

    /**
     * Given a geocoding service that fails (location not found or provider unavailable),
     * when the excursion is created,
     * then the geocoding exception is propagated.
     */
    #[Test]
    #[DataProvider('geocoding_failures')]
    public function throws_the_geocoding_exception_when_geocoding_fails(Throwable $failure): void
    {
        $this->openMeteo->expects($this->once())
            ->method('locateCoordinates')
            ->willThrowException($failure);

        $this->expectException($failure::class);

        $this->service->createExcursion($this->createRequest());
    }

    /**
     * @return iterable<string, array{Throwable}>
     */
    public static function geocoding_failures(): iterable
    {
        yield 'location not found' => [LocationNotFoundException::forCity('Catania', 'IT')];
        yield 'provider unavailable' => [new GeocodingUnavailableException('Open-Meteo geocoding is unavailable.')];
    }

    protected function createRequest(): CreateExcursionRequest
    {
        return new CreateExcursionRequest(
            title: 'Etna sunrise',
            description: 'Guided trek to the summit craters.',
            city: 'Catania',
            country: 'IT',
            date: new DateTime('+7 days'),
            capacity: 10,
        );
    }
}
