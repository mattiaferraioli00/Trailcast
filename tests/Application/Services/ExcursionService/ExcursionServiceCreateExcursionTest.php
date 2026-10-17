<?php

namespace App\Tests\Application\Services\ExcursionService;

use App\Tests\Application\ApplicationTestCase;
use App\Contracts\Services\ExcursionServiceInterface;
use App\Dto\Coordinates;
use App\Entity\Excursion;
use App\Exceptions\LocationNotFoundException;
use App\Exceptions\OpenMeteo\GeocodingUnavailableException;
use App\Http\Requests\CreateExcursionRequest;
use App\Services\ExcursionService;
use DateTime;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

#[CoversMethod(ExcursionService::class, 'createExcursion')]
class ExcursionServiceCreateExcursionTest extends ApplicationTestCase
{
    protected ExcursionServiceInterface $service;

    /**
     * Boots the kernel, replaces Open-Meteo with the fake and reads the service under test.
     */
    protected function setUp(): void
    {
        self::bootKernel();
        $this->prepareApplication();

        $this->service = static::getContainer()->get(ExcursionServiceInterface::class);
    }

    /**
     * Given a request and a geocoding provider that locates the city,
     * when the excursion is created,
     * then it is stored with the request data and the located coordinates.
     */
    #[Test]
    public function stores_the_excursion_with_the_located_coordinates(): void
    {
        $request = $this->request();
        $this->openMeteo->willGeocode(new Coordinates(37.49223, 15.07041));

        $created = $this->service->createExcursion($request);

        $this->entityManager->clear();
        $stored = $this->entityManager->getRepository(Excursion::class)->find($created->getId());

        $this->assertNotNull($stored);
        $this->assertSame('Etna sunrise', $stored->getTitle());
        $this->assertSame('Guided trek to the summit craters.', $stored->getDescription());
        $this->assertSame('Catania', $stored->getCity());
        $this->assertSame('IT', $stored->getCountry());
        $this->assertSame($request->getDate()->format('Y-m-d'), $stored->getDate()->format('Y-m-d'));
        $this->assertSame(10, $stored->getCapacity());
        $this->assertSame(37.49223, $stored->getLatitude());
        $this->assertSame(15.07041, $stored->getLongitude());
    }

    /**
     * Given a new excursion,
     * when it is created,
     * then every seat is available.
     */
    #[Test]
    public function makes_every_seat_available(): void
    {
        $created = $this->service->createExcursion($this->request());

        $this->assertSame(10, $created->getSeatsAvailable());
    }

    /**
     * Given a request,
     * when the excursion is created,
     * then the city is geocoded once, with the city and country of the request.
     */
    #[Test]
    public function geocodes_the_city_once(): void
    {
        $this->service->createExcursion($this->request());

        $this->assertSame([['Catania', 'IT']], $this->openMeteo->geocodeCalls);
    }

    /**
     * Given a geocoding provider that fails (location not found or provider unavailable),
     * when the excursion is created,
     * then the exception is propagated and nothing is stored.
     */
    #[Test]
    #[DataProvider('geocoding_failures')]
    public function throws_the_geocoding_exception_and_stores_nothing(Throwable $failure): void
    {
        $this->openMeteo->willGeocode($failure);

        try {
            $this->service->createExcursion($this->request());
            $this->fail('The geocoding failure was not propagated.');
        } catch (Throwable $e) {
            $this->assertSame($failure, $e);
        }

        $this->assertSame(0, $this->countExcursions());
    }

    /**
     * @return iterable<string, array{Throwable}>
     */
    public static function geocoding_failures(): iterable
    {
        yield 'location not found' => [new LocationNotFoundException('No location found for Catania and IT')];
        yield 'provider unavailable' => [new GeocodingUnavailableException()];
    }

    /**
     * Builds a valid creation request for an excursion in Catania.
     *
     * @return CreateExcursionRequest The request handed to the service.
     */
    protected function request(): CreateExcursionRequest
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
