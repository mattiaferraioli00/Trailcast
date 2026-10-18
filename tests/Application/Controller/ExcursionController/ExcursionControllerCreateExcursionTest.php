<?php

namespace App\Tests\Application\Controller\ExcursionController;

use App\Tests\Application\ApplicationTestCase;
use App\Controller\ExcursionController;
use App\Entity\Excursion;
use App\Entity\User;
use App\Exception\LocationNotFoundException;
use App\Exception\OpenMeteo\GeocodingUnavailableException;
use App\Dto\Coordinates;
use DateTime;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

#[CoversMethod(ExcursionController::class, 'create')]
class ExcursionControllerCreateExcursionTest extends ApplicationTestCase
{
    protected User $staff;

    /**
     * Boots the application with the fake Open-Meteo and a staff user in the database.
     */
    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();

        $this->prepareApplication();
        $this->staff = $this->createUser('staff@trailcast.test', ['ROLE_STAFF']);
    }

    /**
     * Given a staff user and a payload with a city that Open-Meteo locates,
     * when POST /excursions is called,
     * then the response is 201 with the created excursion and the excursion is stored.
     */
    #[Test]
    public function responds_with_201_and_the_created_excursion(): void
    {
        $this->openMeteo->willGeocode(new Coordinates(37.49223, 15.07041));

        $this->callApi('POST', '/excursions', $this->validPayload(), $this->staff);

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $body = $this->responseBody();
        $this->assertIsInt($body['id']);
        $this->assertSame('Etna sunrise', $body['title']);
        $this->assertSame('Guided trek to the summit craters.', $body['description']);
        $this->assertSame('Catania', $body['city']);
        $this->assertSame('IT', $body['country']);
        $this->assertSame((new DateTime('+7 days'))->format('Y-m-d'), $body['date']);
        $this->assertSame(10, $body['capacity']);
        $this->assertSame(10, $body['seats_available']);
        $this->assertSame(37.49223, $body['latitude']);
        $this->assertSame(15.07041, $body['longitude']);

        $this->entityManager->clear();
        $stored = $this->entityManager->getRepository(Excursion::class)->find($body['id']);
        $this->assertNotNull($stored);
        $this->assertSame('Etna sunrise', $stored->getTitle());
    }

    /**
     * Given a valid payload,
     * when POST /excursions is called,
     * then the city and country of the payload are the ones sent to the geocoding provider.
     */
    #[Test]
    public function geocodes_the_city_of_the_payload(): void
    {
        $this->callApi('POST', '/excursions', $this->validPayload(), $this->staff);

        $this->assertResponseStatusCodeSame(201);
        $this->assertSame([['Catania', 'IT']], $this->openMeteo->geocodeCalls);
    }

    /**
     * Given a request without token,
     * when POST /excursions is called,
     * then the response is 401 and nothing is stored.
     */
    #[Test]
    public function responds_with_401_without_a_token(): void
    {
        $this->callApi('POST', '/excursions', $this->validPayload());

        $this->assertResponseStatusCodeSame(401);
        $this->assertSame(0, $this->countExcursions());
        $this->assertSame([], $this->openMeteo->geocodeCalls);
    }

    /**
     * Given a request with a malformed token,
     * when POST /excursions is called,
     * then the response is 401 and nothing is stored.
     */
    #[Test]
    public function responds_with_401_with_an_invalid_token(): void
    {
        $this->client->request(
            'POST',
            '/excursions',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer not-a-valid-token'],
            json_encode($this->validPayload()),
        );

        $this->assertResponseStatusCodeSame(401);
        $this->assertSame(0, $this->countExcursions());
    }

    /**
     * Given an authenticated user who is not staff,
     * when POST /excursions is called,
     * then the response is 403 and nothing is stored.
     */
    #[Test]
    public function responds_with_403_when_the_user_is_not_staff(): void
    {
        $customer = $this->createUser('customer@trailcast.test');

        $this->callApi('POST', '/excursions', $this->validPayload(), $customer);

        $this->assertResponseStatusCodeSame(403);
        $this->assertSame(0, $this->countExcursions());
        $this->assertSame([], $this->openMeteo->geocodeCalls);
    }

    /**
     * Given an invalid payload (blank, too long, unknown country, past date, non positive capacity or missing field),
     * when POST /excursions is called,
     * then the response is 422, the geocoding provider is never called and nothing is stored.
     *
     * @param array<string, mixed> $overrides
     */
    #[Test]
    #[DataProvider('invalid_payloads')]
    public function responds_with_422_and_stores_nothing_when_the_payload_is_invalid(array $overrides): void
    {
        $this->callApi('POST', '/excursions', $this->validPayload($overrides), $this->staff);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame(0, $this->countExcursions());
        $this->assertSame([], $this->openMeteo->geocodeCalls);
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
        yield 'blank description' => [['description' => '']];
        yield 'description too long' => [['description' => str_repeat('a', 501)]];
        yield 'blank city' => [['city' => '']];
        yield 'city too long' => [['city' => str_repeat('a', 256)]];
        yield 'unknown country' => [['country' => 'XX']];
        yield 'past date' => [['date' => '2020-01-01']];
        yield 'today' => [['date' => (new DateTime('today'))->format('Y-m-d')]];
        yield 'malformed date' => [['date' => 'not-a-date']];
        yield 'zero capacity' => [['capacity' => 0]];
        yield 'negative capacity' => [['capacity' => -5]];
        yield 'missing title' => [['title' => null]];
        yield 'missing city' => [['city' => null]];
        yield 'missing country' => [['country' => null]];
        yield 'missing date' => [['date' => null]];
        yield 'missing capacity' => [['capacity' => null]];
    }

    /**
     * Given a geocoding provider that fails (location not found or provider unavailable),
     * when POST /excursions is called,
     * then the response is a problem details JSON with the status of the exception and nothing is stored.
     */
    #[Test]
    #[DataProvider('geocoding_failures')]
    public function responds_with_the_status_of_the_geocoding_failure_and_stores_nothing(
        Throwable $failure,
        int $expectedStatus,
    ): void {
        $this->openMeteo->willGeocode($failure);

        $this->callApi('POST', '/excursions', $this->validPayload(), $this->staff);

        $this->assertResponseStatusCodeSame($expectedStatus);
        $this->assertResponseHeaderSame('Content-Type', 'application/problem+json');

        $body = $this->responseBody();
        $this->assertSame($expectedStatus, $body['status']);
        $this->assertSame($failure->getMessage(), $body['detail']);
        $this->assertSame(0, $this->countExcursions());
    }

    /**
     * @return iterable<string, array{Throwable, int}>
     */
    public static function geocoding_failures(): iterable
    {
        yield 'location not found' => [new LocationNotFoundException('No location found for Catania and IT'), 404];
        yield 'provider unavailable' => [new GeocodingUnavailableException(), 503];
    }

    /**
     * Given a geocoding provider that is unavailable,
     * when POST /excursions is called,
     * then the 503 response tells the client to retry after 10 seconds.
     */
    #[Test]
    public function sends_the_retry_after_header_when_geocoding_is_unavailable(): void
    {
        $this->openMeteo->willGeocode(new GeocodingUnavailableException());

        $this->callApi('POST', '/excursions', $this->validPayload(), $this->staff);

        $this->assertResponseStatusCodeSame(503);
    }

    /**
     * Builds a valid payload; a null override removes the field.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    protected function validPayload(array $overrides = []): array
    {
        $payload = array_merge([
            'title' => 'Etna sunrise',
            'description' => 'Guided trek to the summit craters.',
            'city' => 'Catania',
            'country' => 'IT',
            'date' => (new DateTime('+7 days'))->format('Y-m-d'),
            'capacity' => 10,
        ], $overrides);

        return array_filter($payload, fn (mixed $value): bool => !is_null($value));
    }
}
