<?php

namespace App\Tests\Application\Controller\ExcursionController;

use App\Tests\Application\ApplicationTestCase;
use App\Controller\ExcursionController;
use App\Entity\Excursion;
use DateTime;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[CoversMethod(ExcursionController::class, 'book')]
class ExcursionControllerBookExcursionTest extends ApplicationTestCase
{
    /**
     * Boots the application with the fake Open-Meteo.
     */
    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();

        $this->prepareApplication();
    }

    /**
     * Given an excursion with free seats,
     * when POST /excursions/{id}/bookings is called without authentication,
     * then the response is 201 with the booking and the seats are taken from the excursion.
     */
    #[Test]
    public function responds_with_201_and_the_booking(): void
    {
        $excursion = $this->createExcursion(['capacity' => 10]);

        $this->callApi('POST', "/excursions/{$excursion->getId()}/bookings", $this->validPayload(['seats' => 3]));

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $body = $this->responseBody();
        $this->assertIsInt($body['id']);
        $this->assertSame($excursion->getId(), $body['excursion_id']);
        $this->assertSame('John Doe', $body['customer_name']);
        $this->assertSame('john.doe@example.com', $body['customer_email']);
        $this->assertSame(3, $body['seats']);

        $this->entityManager->clear();
        $this->assertSame(7, $this->entityManager->find(Excursion::class, $excursion->getId())->getSeatsAvailable());
        $this->assertSame(1, $this->countBookings());
    }

    /**
     * Given an excursion with exactly the requested seats left,
     * when POST /excursions/{id}/bookings is called,
     * then the booking is accepted and the excursion is sold out.
     */
    #[Test]
    public function accepts_a_booking_for_the_last_seats(): void
    {
        $excursion = $this->createExcursion(['capacity' => 10, 'seatsAvailable' => 2]);

        $this->callApi('POST', "/excursions/{$excursion->getId()}/bookings", $this->validPayload(['seats' => 2]));

        $this->assertResponseStatusCodeSame(201);

        $this->entityManager->clear();
        $this->assertSame(0, $this->entityManager->find(Excursion::class, $excursion->getId())->getSeatsAvailable());
    }

    /**
     * Given an id that matches no excursion,
     * when POST /excursions/{id}/bookings is called,
     * then the response is 404 and nothing is stored.
     */
    #[Test]
    public function responds_with_404_when_the_excursion_does_not_exist(): void
    {
        $this->callApi('POST', '/excursions/999999/bookings', $this->validPayload());

        $this->assertResponseStatusCodeSame(404);
        $this->assertSame(0, $this->countBookings());
    }

    /**
     * Given an id that is not a number,
     * when POST /excursions/{id}/bookings is called,
     * then the response is 404 because no route matches.
     */
    #[Test]
    public function responds_with_404_when_the_id_is_not_numeric(): void
    {
        $this->callApi('POST', '/excursions/abc/bookings', $this->validPayload());

        $this->assertResponseStatusCodeSame(404);
    }

    /**
     * Given an excursion with fewer seats than requested,
     * when POST /excursions/{id}/bookings is called,
     * then the response is a 409 problem details JSON naming the excursion and the seats, and nothing changes.
     */
    #[Test]
    public function responds_with_409_when_there_are_not_enough_seats(): void
    {
        $excursion = $this->createExcursion(['capacity' => 10, 'seatsAvailable' => 2]);

        $this->callApi('POST', "/excursions/{$excursion->getId()}/bookings", $this->validPayload(['seats' => 3]));

        $this->assertResponseStatusCodeSame(409);
        $this->assertResponseHeaderSame('Content-Type', 'application/problem+json');

        $body = $this->responseBody();
        $this->assertSame(409, $body['status']);
        $this->assertSame('Errore nella richiesta', $body['title']);
        $this->assertSame($excursion->getId(), $body['params']['excursion_id']);
        $this->assertSame(3, $body['params']['seats']);

        $this->entityManager->clear();
        $this->assertSame(2, $this->entityManager->find(Excursion::class, $excursion->getId())->getSeatsAvailable());
        $this->assertSame(0, $this->countBookings());
    }

    /**
     * Given an excursion whose date has passed,
     * when POST /excursions/{id}/bookings is called,
     * then the response is 409 and nothing is stored.
     */
    #[Test]
    public function responds_with_409_when_the_excursion_is_in_the_past(): void
    {
        $excursion = $this->createExcursion(['date' => new DateTime('-2 days')]);

        $this->callApi('POST', "/excursions/{$excursion->getId()}/bookings", $this->validPayload());

        $this->assertResponseStatusCodeSame(409);
        $this->assertSame(0, $this->countBookings());
    }

    /**
     * Given an invalid payload (blank or too long name, malformed email, non positive or missing seats, missing field),
     * when POST /excursions/{id}/bookings is called,
     * then the response is 422 and nothing is stored.
     *
     * @param array<string, mixed> $overrides
     */
    #[Test]
    #[DataProvider('invalid_payloads')]
    public function responds_with_422_and_stores_nothing_when_the_payload_is_invalid(array $overrides): void
    {
        $excursion = $this->createExcursion(['capacity' => 10]);

        $this->callApi('POST', "/excursions/{$excursion->getId()}/bookings", $this->validPayload($overrides));

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame(0, $this->countBookings());

        $this->entityManager->clear();
        $this->assertSame(10, $this->entityManager->find(Excursion::class, $excursion->getId())->getSeatsAvailable());
    }

    /**
     * A null override removes the field from the payload.
     *
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalid_payloads(): iterable
    {
        yield 'blank name' => [['customer_name' => '']];
        yield 'name too long' => [['customer_name' => str_repeat('a', 256)]];
        yield 'malformed email' => [['customer_email' => 'not-an-email']];
        yield 'blank email' => [['customer_email' => '']];
        yield 'zero seats' => [['seats' => 0]];
        yield 'negative seats' => [['seats' => -1]];
        yield 'missing name' => [['customer_name' => null]];
        yield 'missing email' => [['customer_email' => null]];
        yield 'missing seats' => [['seats' => null]];
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
            'customer_name' => 'John Doe',
            'customer_email' => 'john.doe@example.com',
            'seats' => 1,
        ], $overrides);

        return array_filter($payload, fn (mixed $value): bool => !is_null($value));
    }
}
