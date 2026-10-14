<?php

namespace App\Tests\Application\Controller\ExcursionController;

use App\Contracts\Services\OpenMeteoServiceInterface;
use App\Controller\ExcursionController;
use App\Dto\Coordinates;
use App\Entity\Excursion;
use App\Exceptions\LocationNotFoundException;
use App\Exceptions\OpenMeteo\GeocodingUnavailableException;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Throwable;

#[CoversMethod(ExcursionController::class, 'create')]
class CreateExcursionTest extends WebTestCase
{
    protected KernelBrowser $client;

    protected OpenMeteoServiceInterface&MockObject $openMeteo;

    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();

        $this->openMeteo = $this->createMock(OpenMeteoServiceInterface::class);
        $container->set(OpenMeteoServiceInterface::class, $this->openMeteo);
        $this->entityManager = $container->get(EntityManagerInterface::class);
    }

    /**
     * Given a valid payload and a geocoding service that locates the city,
     * when POST /excursions is called,
     * then the response is 201 with the created excursion and the excursion is stored in the database.
     */
    #[Test]
    public function responds_with_201_and_stores_the_excursion(): void
    {
        $this->openMeteo->expects($this->once())
            ->method('locateCoordinates')
            ->with('Catania', 'IT')
            ->willReturn(new Coordinates(37.49223, 15.07041));

        $this->postJson($this->validPayload());

        $this->assertResponseStatusCodeSame(201);

        $body = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Etna sunrise', $body['title']);
        $this->assertSame('Catania', $body['city']);
        $this->assertSame('IT', $body['country']);
        $this->assertSame(10, $body['capacity']);
        $this->assertSame(37.49223, $body['latitude']);
        $this->assertSame(15, $body['longitude']);

        $this->entityManager->clear();
        $stored = $this->entityManager->getRepository(Excursion::class)->find($body['id']);

        $this->assertNotNull($stored);
        $this->assertSame('Etna sunrise', $stored->getTitle());
        $this->assertSame(37.49223, $stored->getLatitude());
        $this->assertSame(15, $stored->getLongitude());
    }

    /**
     * Given an invalid payload (blank, too long, unknown country, past date, non positive capacity or missing field),
     * when POST /excursions is called,
     * then the response is 422, the geocoding service is never called and nothing is stored.
     */
    #[Test]
    #[DataProvider('invalid_payloads')]
    public function responds_with_422_and_stores_nothing_when_the_payload_is_invalid(array $overrides): void
    {
        $this->openMeteo->expects($this->never())->method('locateCoordinates');

        $this->postJson($this->validPayload($overrides));

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame(0, $this->countStoredExcursions());
    }

    /**
     * A null override removes the field from the payload.
     *
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalid_payloads(): iterable
    {
        yield 'blank title' => [['title' => '']];
        yield 'title too long' => [['title' => str_repeat('a', 256)]];
        yield 'description too long' => [['description' => str_repeat('a', 501)]];
        yield 'blank city' => [['city' => '']];
        yield 'unknown country' => [['country' => 'XX']];
        yield 'past date' => [['date' => '01-01-2020']];
        yield 'malformed date' => [['date' => 'not-a-date']];
        yield 'zero capacity' => [['capacity' => 0]];
        yield 'negative capacity' => [['capacity' => -5]];
        yield 'missing city' => [['city' => null]];
        yield 'missing capacity' => [['capacity' => null]];
    }

    /**
     * Given a geocoding service that fails (location not found or provider unavailable),
     * when POST /excursions is called,
     * then the response carries the status of the exception and nothing is stored.
     */
    #[Test]
    #[DataProvider('geocoding_failures')]
    public function responds_with_the_exception_status_and_stores_nothing_when_geocoding_fails(
        Throwable $failure,
        int       $expectedStatus
    ): void
    {
        $this->openMeteo->expects($this->once())
            ->method('locateCoordinates')
            ->willThrowException($failure);

        $this->postJson($this->validPayload());

        $this->assertResponseStatusCodeSame($expectedStatus);
        $this->assertSame(0, $this->countStoredExcursions());
    }

    /**
     * @return iterable<string, array{Throwable, int}>
     */
    public static function geocoding_failures(): iterable
    {
        yield 'location not found' => [LocationNotFoundException::forCity('Catania', 'IT'), 404];
        yield 'provider unavailable' => [new GeocodingUnavailableException('Open-Meteo geocoding is unavailable.'), 503];
    }

    /**
     * Given a geocoding provider that is unavailable,
     * when POST /excursions is called,
     * then the 503 response tells the client to retry after 10 seconds.
     */
    #[Test]
    public function sends_the_retry_after_header_when_geocoding_is_unavailable(): void
    {
        $this->openMeteo->expects($this->once())
            ->method('locateCoordinates')
            ->willThrowException(new GeocodingUnavailableException('Open-Meteo geocoding is unavailable.'));

        $this->postJson($this->validPayload());

        $this->assertResponseHeaderSame('Retry-After', '10');
    }

    /**
     * @param array<string, mixed> $payload
     *
     * // TODO: mettere metodo nel TestCase Application e renderlo dinamico in base al metodo
     */
    protected function postJson(array $payload): void
    {
        $this->client->request(
            'POST',
            '/api/excursions',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );
    }

    /**
     * Builds a valid payload; a null override removes the field.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    protected function validPayload(array $overrides = []): array
    {
        $payload = array_merge([
            'title' => 'Etna sunrise',
            'description' => 'Guided trek to the summit craters.',
            'city' => 'Catania',
            'country' => 'IT',
            'date' => (new DateTime('+7 days'))->format('d-m-Y'),
            'capacity' => 10,
        ], $overrides);

        return array_filter($payload, fn(mixed $value): bool => $value !== null);
    }

    protected function countStoredExcursions(): int
    {
        return $this->entityManager->getRepository(Excursion::class)->count([]);
    }
}
